<?php

namespace Vox\Webservice;

use Doctrine\Common\Collections\Collection;
use Metadata\MetadataFactoryInterface;
use Traversable;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Event\DispatchEventTrait;
use Vox\Webservice\Event\LifecycleEvent;
use Vox\Webservice\Event\PersistenceEvents;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\HasMany;
use Vox\Webservice\Mapping\HasOne;
use Vox\Webservice\Mapping\Resource;
use Vox\Webservice\Metadata\TransferMetadata;

/**
 * The transfer persister will do the work of persisting and assuring the objects are on the
 * correct state
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class TransferPersister implements TransferPersisterInterface
{
    use MetadataTrait, 
        DispatchEventTrait;
    
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;
    
    /**
     * @var UnityOfWorkInterface
     */
    private $unitOfWork;
    
    /**
     * @var WebserviceClientInterface
     */
    private $webserviceClient;
    
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;
    
    /**
     * @var TransferManagerInterface
     */
    private $transferManager;
    
    public function __construct(
        MetadataFactoryInterface $metadataFactory,
        UnitOfWorkInterface $unitOfWork,
        WebserviceClientInterface $webserviceClient,
        TransferManagerInterface $transferManager = null,
        EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->metadataFactory  = $metadataFactory;
        $this->unitOfWork       = $unitOfWork;
        $this->webserviceClient = $webserviceClient;
        $this->transferManager  = $transferManager;
        $this->eventDispatcher  = $eventDispatcher;
    }

    public function save($object, $owner = null)
    {
        $transfer = $object;
        
        $metadata = $this->getClassMetadata($transfer);
        
        foreach ($metadata->associations as $name => $association) {
            $assocValue = $association->getValue($transfer);
            
            if (!$assocValue) {
                continue;
            }
            
            $this->persistAssociation($object, $assocValue, $metadata, $association);
        }
        
        $this->persistTransfer($object, $owner);
    }
    
    private function persistAssociation($object, $association, TransferMetadata $metadata, PropertyMetadata $property)
    {
        if (is_array($association) || $association instanceof Traversable || $association instanceof Collection) {
            foreach ($association as $transfer) {
                $this->persistAssociation($object, $transfer, $metadata, $property);
            }

            return;
        }

        if ($this->unitOfWork->isDetached($association)) {
            $this->unitOfWork->attach($association);
        }

        if (!$this->unitOfWork->isNew($association) && !$this->unitOfWork->isDirty($association)) {
            return;
        }

        $this->save($association, $object);
    }

    private function persistTransfer($object, $owner = null)
    {
        $event = $this->eventDispatcher ? new LifecycleEvent($object, $this->transferManager) : null;
        
        if ($this->unitOfWork->isNew($object)) {
            $this->updateRelationshipsIds($object, $owner);
            $this->dispatchEvent(PersistenceEvents::PRE_PERSIST, $event);
            $this->webserviceClient->post($object);
            $this->renewState($object);
            $this->dispatchEvent(PersistenceEvents::POST_PERSIST, $event);
            
            return;
        }

        if ($this->unitOfWork->isRemoved($object)) {
            $this->dispatchEvent(PersistenceEvents::PRE_REMOVE, $event);
            $this->webserviceClient->delete(get_class($object), $this->getIdValue($object));
            $this->unitOfWork->detach($object);
            $this->dispatchEvent(PersistenceEvents::POST_REMOVE, $event);

            return;
        }

        if ($this->unitOfWork->isDirty($object)) {
            $this->updateRelationshipsIds($object, $owner);
            $this->dispatchEvent(PersistenceEvents::PRE_UPDATE, $event);
            $this->webserviceClient->put($object);
            $this->renewState($object);
            $this->dispatchEvent(PersistenceEvents::POST_UPDATE, $event);
        }
    }

    private function updateRelationshipsIds($object, $owner = null)
    {
        if ($owner) {
            $this->updateRelationshipsIds($owner);
        }

        $objectMetadata = $this->getClassMetadata($object);

        foreach ($objectMetadata->associations as $associationProperty) {
            $association = $associationProperty->getValue($object);

            if (!$association) {
                continue;
            }

            $associationMetadata = $this->getClassMetadata($association);

            if ($associationProperty->hasAnnotation(BelongsTo::class)) {
                if ($associationMetadata->id->isMultiId()) {
                    $this->updateMultiBelongsToIds($object, $association);
                } else {
                    $this->updateBelogsToId($object, $association, $associationProperty);
                }

                continue;
            }

            if ($associationProperty->hasAnnotation(HasOne::class)) {
                $this->updateHasOneId($object, $association, $associationProperty->getAnnotation(HasOne::class));

                continue;
            }

            if ($associationProperty->hasAnnotation(HasMany::class)) {
                $this->updateHasManyIds($object, $association, $associationProperty->getAnnotation(HasMany::class));

                continue;
            }
        }
    }

    private function updateMultiBelongsToIds($object, $association)
    {
        $id             = $this->getIdValue($association);
        $objectMetadata = $this->getClassMetadata($object);

        if (empty($id)) {
            foreach ($this->getClassMetadata($association)->id->getIds() as $idProperty) {
                $idProperty->setValue(
                    $association,
                    $objectMetadata->propertyMetadata[$idProperty->name]->getValue($object)
                );
            }
        } else {
            foreach ($this->getClassMetadata($association)->id->getIds() as $idProperty) {
                $objectMetadata->propertyMetadata[$idProperty->name]
                    ->setValue($object, $idProperty->getValue($association));
            }
        }
    }

    private function updateBelogsToId($object, $association, PropertyMetadata $associationProperty)
    {
        /* @var $belongsTo BelongsTo */
        $belongsTo = $associationProperty->getAnnotation(BelongsTo::class);

        $objectMetadata = $this->getClassMetadata($object);

        $foreignPropertyMetadata = $objectMetadata->propertyMetadata[$belongsTo->foreignField];
        $idValue                 = $this->getIdValue($association);
        
        if ($foreignPropertyMetadata->type == 'string') {
            $idValue = sprintf('%s/%s', $objectMetadata->getAnnotation(Resource::class)->route, $idValue);
        }
        
        $foreignPropertyMetadata->setValue($object, $idValue);
    }

    /**
     * @param $object
     * @param $association
     * @param HasOne|HasMany $annotation
     */
    private function updateHasOneId($object, $association, $annotation)
    {
        $id                  = $this->getIdValue($object);
        $associationMetadata = $this->getClassMetadata($association);

        $foreignPropertyMetadata = $associationMetadata->propertyMetadata[$annotation->foreignField];
        
        if ($foreignPropertyMetadata->type == 'string') {
            $objectMetadata = $this->getClassMetadata($object);
            $id = sprintf('%s/%s', $objectMetadata->getAnnotation(Resource::class)->route, $id);
        }
        
        $foreignPropertyMetadata->setValue($association, $id);
    }

    /**
     * @param $object
     * @param Traversable $associations
     * @param HasOne|HasMany $annotation
     */
    private function updateHasManyIds($object, Traversable $associations, $annotation)
    {
        if (!empty($annotation->foreignField)) {
            foreach ($associations as $association) {
                $this->updateHasOneId($object, $association, $annotation);
            }
        }
        
        if (!$annotation->iriCollectionField) {
            return;
        }
        
        $iris = [];
        
        $objectMetadata = $this->getClassMetadata($object);
        
        foreach ($associations as $association) {
            $resource = $this->getClassMetadata($association)->getAnnotation(Resource::class);
            $iris[] = sprintf('%s/%s', $resource->route, $this->getIdValue($association));
        }
        
        $objectMetadata->propertyMetadata[$annotation->iriCollectionField]->setValue($object, $iris);
    }

    private function renewState($object)
    {
        $this->unitOfWork->detach($object);
        $this->unitOfWork->attach($object);
    }
}
