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

    
    public function save($object)
    {
        $transfer = $object;
        
        if ($object instanceof AccessInterceptorValueHolderInterface) {
            $transfer = $transfer->getWrappedValueHolderValue();
        }
        
        $metadata = $this->getClassMetadata($transfer);
        
        foreach ($metadata->associations as $name => $association) {
            $assocValue = $association->getValue($transfer);
            
            if (!$assocValue) {
                continue;
            }
            
            $this->persistAssociation($object, $assocValue, $metadata, $association);
        }
        
        $this->persistTransfer($object);
    }
    
    private function persistAssociation($object, $association, TransferMetadata $metadata, PropertyMetadata $property)
    {
        if (is_array($association) || $association instanceof Traversable || $association instanceof Collection) {
            foreach ($association as $transfer) {
                $this->persistAssociation($object, $transfer, $metadata, $property);
            }

            return;
        }
        
        if (!$this->unityOfWork->isNew($association) && !$this->unityOfWork->isDirty($association)) {
            return;
        }
        
        $this->save($association);
        
        if ($property->hasAnnotation(BelongsTo::class)) {
            $this->persistBelongsTo($object, $association, $metadata, $property);
        }
        
        if ($property->hasAnnotation(HasOne::class)) {
            $this->persistHas($object, $association, $property, $property->getAnnotation(HasOne::class));
        }
        
        if ($property->hasAnnotation(HasMany::class)) {
            $this->persistHas($object, $association, $property, $property->getAnnotation(HasMany::class));
        }
    }
    
    private function persistBelongsTo($object, $association, TransferMetadata $metadata, PropertyMetadata $property)
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
    
    private function persistHas($object, $association, PropertyMetadata $property, $annotation) 
    {
        $type                  = preg_replace('/\[\]$/', '', $property->type);
        $relationClassMetadata = $this->metadataFactory->getMetadataForClass($type);
        $relationObject        = $property->getValue($object);
        $foreignProperty       = $relationClassMetadata->propertyMetadata[$annotation->foreignField];

        if ($relationObject instanceof Traversable || is_array($relationObject)) {
            foreach ($relationObject as $relationItem) {
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
    
    private function persistTransfer($object)
    {
        if ($this->unityOfWork->isNew($object)) {
            $this->webserviceClient->post($object);
            $this->renewState($object);
            
            return;
        }
        
        if ($this->unityOfWork->isDirty($object)) {
            $this->webserviceClient->put($object);
            $this->renewState($object);
            
            return;
        }
        
        if ($this->unityOfWork->isRemoved($object)) {
            $this->webserviceClient->delete(get_class($object), $this->getIdValue($object));
        }
    }
    
    private function renewState($object)
    {
        $this->unityOfWork->detach($object);
        $this->unityOfWork->attach($object);
    }
}
