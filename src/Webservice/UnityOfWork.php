<?php

namespace Vox\Webservice;

use AppendIterator;
use BadMethodCallException;
use Metadata\MetadataFactoryInterface;
use SplObjectStorage;

/**
 * the unit of work keeps track of the transfers current state, works as a sort of memento pattern
 * its really important part of the persistence proccess
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
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
    
    public function __construct(MetadataFactoryInterface $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
        $this->cleanData       = new ObjectStorage($metadataFactory);
        $this->data            = new ObjectStorage($metadataFactory);
        $this->newObjects      = new SplObjectStorage();
        $this->removedObjects  = new SplObjectStorage();
    }
    
    public function contains($object): bool
    {
        return $this->data->contains($object)
            || $this->newObjects->contains($object);
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
        if ($this->cleanData->contains($object)) {
            $this->cleanData->detach($object);
        }
        
        if ($this->data->contains($object)) {
            $this->data->detach($object);
        }
        
        if ($this->newObjects->contains($object)) {
            $this->newObjects->detach($object);
        }
    }
    
    public function remove($object)
    {
        if (!$this->contains($object)) {
            throw new \RuntimeException('object ' . get_class($object) . ' is not managed');
        }
        
        if (!$this->removedObjects->contains($object)) {
            $this->removedObjects->attach($object);
        }
        
        $this->detach($object);
    }
    
    public function isNew($object): bool
    {
        return empty($this->getIdValue($object)) 
            && ($this->newObjects->contains($object)
                || !$this->cleanData->contains($object));
    }
    
    public function isDirty($object): bool
    {
        return $this->data->contains($object)
            && !$this->cleanData->isEquals($object);
    }
    
    public function isDetached($object): bool
    {
        return !empty($this->getIdValue($object))
            && !$this->cleanData->contains($object);
    }

    public function getIterator()
    {
        $iterator = new AppendIterator();
        $iterator->append(new \ArrayIterator(iterator_to_array($this->newObjects)));
        $iterator->append($this->data->getIterator());
        $iterator->append(new \ArrayIterator(iterator_to_array($this->removedObjects)));
        
        return $iterator;
    }

    public function isEquals($object): bool
    {
        throw new BadMethodCallException('not implemented');
    }

    public function isRemoved($object): bool
    {
        return $this->removedObjects->contains($object);
    }

    /**
     * @param string $className
     * @param scalar $id
     *
     * @return object
     */
    public function fetchByParams(...$params)
    {
        return $this->data->fetchByParams(...$params);
    }
}
