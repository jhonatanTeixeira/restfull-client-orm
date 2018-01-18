<?php

namespace Vox\Webservice;

use Doctrine\Common\Collections\Collection;
use Metadata\MetadataFactoryInterface;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use Traversable;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\HasMany;
use Vox\Webservice\Mapping\HasOne;
use Vox\Webservice\Metadata\TransferMetadata;
use Zend\Code\Exception\RuntimeException;

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

    private function updateRelationshipsIds($object, $owner)
    {
        $objectMetadata = $this->getClassMetadata($object);

        if ($owner && $objectMetadata->id->isMultiId()) {
            $ownerMetadata = $this->getClassMetadata($owner);

            foreach ($ownerMetadata->associations as $association) {
                if ($association->type == $objectMetadata->name) {
                    $this->updateMultiBelongsToIds($owner, $object);
                }
            }
        }

        foreach ($objectMetadata->associations as $associationMetadata) {
            $association = $associationMetadata->getValue($object);

            if ($association && $associationMetadata->hasAnnotation(BelongsTo::class)) {
                $this->updateBelongsToId($object, $association, $objectMetadata, $associationMetadata);

                return;
            }

            if ($association){
                if ($associationMetadata->hasAnnotation(HasMany::class)) {
                    $annotation = $associationMetadata->getAnnotation(HasMany::class);
                } elseif ($associationMetadata->hasAnnotation(HasOne::class)) {
                    $annotation = $associationMetadata->getAnnotation(HasOne::class);
                } else {
                    throw new RuntimeException('inavlid relationship declaration');
                }

                $this->updateHasIds($object, $association, $associationMetadata, $annotation);
            }
        }
    }

    private function updateBelongsToId($object, $association, TransferMetadata $metadata, PropertyMetadata $property)
    {
        /* @var $belongsTo BelongsTo */
        $belongsTo       = $property->getAnnotation(BelongsTo::class);
        $foreignProperty = $metadata->propertyMetadata[$belongsTo->foreignField];
        $foreignId       = $foreignProperty->getValue($object);
        $currentId       = $this->getIdValue($association);

        if ($foreignId !== $currentId) {
            $foreignProperty->setValue($object, $currentId);
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
    
    private function updateHasIds($object, $association, PropertyMetadata $property, $annotation)
    {
        $type                  = preg_replace('/\[\]$/', '', $property->type);
        $relationClassMetadata = $this->metadataFactory->getMetadataForClass($type);
        $foreignProperty       = $relationClassMetadata->propertyMetadata[$annotation->foreignField];

        if ($association instanceof Traversable || is_array($association)) {
            foreach ($association as $relationItem) {
                $this->setIdValueOnAssociation($object, $relationItem, $foreignProperty);
            }

            return;
        }

        $this->setIdValueOnAssociation($object, $association, $foreignProperty);
    }

    private function setIdValueOnAssociation($object, $association, PropertyMetadata $foreignProperty)
    {
        $assocId  = $foreignProperty->getValue($association);
        $objectId = $this->getIdValue($object);

        if ($assocId !== $objectId) {
            $foreignProperty->setValue($association, $objectId);
        }
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
    
    private function renewState($object)
    {
        $this->unityOfWork->detach($object);
        $this->unityOfWork->attach($object);
    }
}
