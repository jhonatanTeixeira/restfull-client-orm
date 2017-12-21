<?php

namespace Vox\Webservice;

use InvalidArgumentException;
use Metadata\MetadataFactoryInterface;
use BadMethodCallException;

/**
 * A object storage using an id hashmap pattern
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
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
        $items = [];
        
        foreach ($this->storage as $transferData) {
            foreach ($transferData as $item) {
                $items[] = $item;
            }
        }
        
        return new \ArrayIterator($items);
    }

    public function isEquals($object): bool
    {
        return $object == $this->storage[get_class($object)][$this->getIdValue($object)];
    }

    /**
     * @param string $className
     * @param scalar $id
     *
     * @return object
     */
    public function fetchByParams(...$params)
    {
        if (count($params) != 2) {
            throw new BadMethodCallException('this method needs two params, $className: string, $id: scalar');
        }

        $className = (string) $params[0];
        $id        = $params[1];

        if (!is_scalar($id)) {
            throw new BadMethodCallException('$id must be scalar value');
        }

        return $this->storage[$className][$id] ?? null;
    }
}
