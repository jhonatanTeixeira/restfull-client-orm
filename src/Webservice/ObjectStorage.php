<?php

namespace Vox\Webservice;

use InvalidArgumentException;
use Metadata\MetadataFactoryInterface;

class ObjectStorage implements ObjectStorageInterface
{
    use MetadataTrait;
    
    private $storage = [];
    
    public function __construct(MetadataFactoryInterface $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }
    
    public function contains($object): bool
    {
        return isset($this->storage[get_class($object)][$this->getIdValue($object)]);
    }
    
    public function attach($object)
    {
        $className = get_class($object);
        $id        = $this->getIdValue($object);
        
        if (!$id) {
            throw new InvalidArgumentException('object has no id value');
        }
        
        $this->storage[$className][$id] = $object;
    }
    
    public function detach($object)
    {
        unset($this->storage[get_class($object)][$this->getIdValue($object)]);
    }

    public function getIterator()
    {
        foreach ($this->storage as $transferData) {
            foreach ($transferData as $item) {
                yield $item;
            }
        }
    }

    public function isEquals($object): bool
    {
        return $object == $this->storage[get_class($object)][$this->getIdValue($object)];
    }
}
