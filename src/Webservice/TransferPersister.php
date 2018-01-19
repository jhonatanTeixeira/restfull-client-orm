<?php

namespace Vox\Webservice;

use Doctrine\Common\Collections\Collection;
use Metadata\MetadataFactoryInterface;
use Traversable;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\HasMany;
use Vox\Webservice\Mapping\HasOne;
use Vox\Webservice\Metadata\TransferMetadata;

/**
 * The transfer persister will do the work of persisting and assuring the objects are on the
 * correct state
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class TransferPersister implements TransferPersisterInterface
{
    use MetadataTrait;
    
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;
    
    /**
     * @var UnityOfWorkInterface
     */
    private $unityOfWork;
    
    /**
     * @var WebserviceClientInterface
     */
    private $webserviceClient;
    
    public function __construct(
        MetadataFactoryInterface $metadataFactory,
        UnityOfWorkInterface $unityOfWork,
        WebserviceClientInterface $webserviceClient
    ) {
        $this->metadataFactory  = $metadataFactory;
        $this->unityOfWork      = $unityOfWork;
        $this->webserviceClient = $webserviceClient;
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

        if ($this->unityOfWork->isDetached($association)) {
            $this->unityOfWork->attach($association);
        }

        if (!$this->unityOfWork->isNew($association) && !$this->unityOfWork->isDirty($association)) {
            return;
        }

        $this->save($association, $object);
    }

    private function persistTransfer($object, $owner = null)
    {
        if ($this->unityOfWork->isNew($object)) {
            $this->updateRelationshipsIds($object, $owner);
            $this->webserviceClient->post($object);
            $this->renewState($object);
            
            return;
        }

        if ($this->unityOfWork->isRemoved($object)) {
            $this->webserviceClient->delete(get_class($object), $this->getIdValue($object));
            $this->unityOfWork->detach($object);

            return;
        }

        if ($this->unityOfWork->isDirty($object)) {
            $this->updateRelationshipsIds($object, $owner);
            $this->webserviceClient->put($object);
            $this->renewState($object);
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

        $objectMetadata->propertyMetadata[$belongsTo->foreignField]
            ->setValue($object, $this->getIdValue($association));
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

        $associationMetadata->propertyMetadata[$annotation->foreignField]
            ->setValue($association, $id);
    }

    /**
     * @param $object
     * @param Traversable $associations
     * @param HasOne|HasMany $annotation
     */
    private function updateHasManyIds($object, Traversable $associations, $annotation)
    {
        foreach ($associations as $association) {
            $this->updateHasOneId($object, $association, $annotation);
        }
    }

    private function renewState($object)
    {
        $this->unityOfWork->detach($object);
        $this->unityOfWork->attach($object);
    }
}
