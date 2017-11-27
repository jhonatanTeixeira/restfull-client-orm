<?php

namespace Vox\Webservice;

use AppendIterator;
use BadMethodCallException;
use Metadata\MetadataFactoryInterface;
use RuntimeException;
use SplObjectStorage;
use Vox\Webservice\Metadata\TransferMetadata;

class UnityOfWork implements UnityOfWorkInterface
{
    use MetadataTrait;
    
    /**
     * @var ObjectStorageInterface
     */
    private $cleanData;
    
    /**
     * @var ObjectStorageInterface
     */
    private $data;
    
    /**
     * @var SplObjectStorage
     */
    private $newObjects;
    
    /**
     * @var SplObjectStorage
     */
    private $removedObjects;
    
    /**
     * @var WebserviceClientInterface
     */
    private $webserviceClient;
    
    public function __construct(WebserviceClientInterface $webserviceClient, MetadataFactoryInterface $metadataFactory)
    {
        $this->webserviceClient = $webserviceClient;
        $this->metadataFactory  = $metadataFactory;
        
        $this->cleanData      = new ObjectStorage($metadataFactory);
        $this->data           = new ObjectStorage($metadataFactory);
        $this->newObjects     = new SplObjectStorage();
        $this->removedObjects = new SplObjectStorage();
    }
    
    public function contains($object): bool
    {
        return $this->cleanData->contains($object);
    }
    
    public function attach($object)
    {
        $id = $this->getIdValue($object);
        
        if (!$id) {
            $this->newObjects->attach($object);
            
            return;
        }
        
        if (!$this->cleanData->contains($object)) {
            $this->cleanData->attach(clone $object);
        }
        
        if (!$this->data->contains($object)) {
            $this->data->attach($object);
        }
    }
    
    public function detach($object)
    {
        if ($this->data->contains($object)) {
            $this->data->detach($object);
        }
        
        if ($this->data->contains($object)) {
            $this->data->detach($object);
        }
        
        if ($this->newObjects->contains($object)) {
            $this->newObjects->detach($object);
        }
        
        if (!$this->removedObjects->contains($object)) {
            $this->removedObjects->attach($object);
        }
    }
    
    private function getIdValue($object)
    {
        $id = $this->getClassMetadata($object)->id;
        
        if (!$id) {
            throw new RuntimeException("transfer " . get_class($object) . " has no id mapping");
        }
        
        return $id->getValue($object);
    }
    
    private function getClassMetadata($object): TransferMetadata
    {
        if (is_object($object)) {
            $object = get_class($object);
        }
        
        return $this->metadataFactory->getMetadataForClass($object);
    }
    
    public function flush()
    {
        foreach ($this->newObjects as $newTransfer) {
            $this->webserviceClient->post($newTransfer);
        }
        
        foreach ($this->data as $transfer) {
            if ($this->cleanData->isEquals($transfer)) {
                continue;
            }
            
            $this->webserviceClient->put($transfer);
        }
        
        foreach ($this->removedObjects as $removedObject) {
            $this->webserviceClient->delete(get_class($removedObject), $this->getIdValue($removedObject));
        }
    }

    public function getIterator()
    {
        $iterator = new AppendIterator();
        $iterator->append($this->newObjects);
        $iterator->append($this->data);
        
        return $iterator;
    }

    public function isEquals($object): bool
    {
        throw new BadMethodCallException('not implemented');
    }
}
