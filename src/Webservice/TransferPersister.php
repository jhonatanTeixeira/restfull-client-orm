<?php

namespace Vox\Webservice;

use Metadata\MetadataFactoryInterface;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Metadata\TransferMetadata;

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
            $assocTransfer = $association->getValue($transfer);
            
            if (!$assocTransfer) {
                continue;
            }
            
            $this->persistAssociation($object, $assocTransfer, $metadata, $association);
        }
        
        $this->persistTransfer($object);
    }
    
    private function persistAssociation($object, $association, TransferMetadata $metadata, \Vox\Metadata\PropertyMetadata $property)
    {
        if (!$this->unityOfWork->isNew($association) && !$this->unityOfWork->isDirty($association)) {
            return;
        }
        
        $this->save($association);
        
        /* @var $belongsTo BelongsTo */
        $belongsTo       = $property->getAnnotation(BelongsTo::class);
        $foreignProperty = $metadata->propertyMetadata[$belongsTo->foreignField];
        $foreignId       = $foreignProperty->getValue($object);
        $currentId       = $this->getIdValue($association);

        if ($foreignId !== $currentId) {
            $foreignProperty->setValue($object, $currentId);
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
