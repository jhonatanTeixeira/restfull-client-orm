<?php

namespace Vox\Webservice;

use Closure;
use Doctrine\Common\Collections\Collection;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Vox\Webservice\Proxy\ProxyFactoryInterface;

/**
 * Transfer collection is used as a way to keep the objects inside the unity of work and proxyed
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class TransferCollection implements Collection
{
    private $transferName;
    
    /**
     * @var DenormalizerInterface
     */
    private $denormalizer;
    
    /**
     * @var ObjectStorageInterface
     */
    private $objectStorage;
    
    /**
     * @var ProxyFactoryInterface
     */
    private $proxyFactory;
    
    /**
     * @var TransferManagerInterface
     */
    private $transferManager;

    private $items = [];

    private $iterator;
    
    public function __construct(string $transferName, DenormalizerInterface $denormalizer, ResponseInterface $response)
    {
        $this->items = json_decode($response->getBody()->getContents() ?: '[]', true);
        
        $this->iterator = function () {
            foreach ($this->items as $key => $item) {
                yield $key => $this->createTransfer($item);
            }
        };

        $this->transferName = $transferName;
        $this->denormalizer = $denormalizer;
    }

    private function createTransfer($data)
    {
        if (!is_object($data)) {
            $data = $this->denormalizer->denormalize($data, $this->transferName);

            if ($this->proxyFactory && $this->transferManager) {
                $data = $this->proxyFactory->createProxy($data, $this->transferManager);
            }
        }

        if (isset($this->objectStorage) && !$this->objectStorage->contains($data)) {
            $this->objectStorage->attach($data);
        }

        return $data;
    }

    public function setObjectStorage(ObjectStorageInterface $objectStorage)
    {
        $this->objectStorage = $objectStorage;
        
        return $this;
    }
    
    public function setProxyFactory(ProxyFactoryInterface $proxyFactory)
    {
        $this->proxyFactory = $proxyFactory;
        
        return $this;
    }

    public function setTransferManager(TransferManagerInterface $transferManager)
    {
        $this->transferManager = $transferManager;
        
        return $this;
    }

    public function add($element)
    {
        $this->items[] = $element;

        return $this;
    }

    public function clear()
    {
        $this->items = [];
    }

    public function contains($element): bool
    {
        return in_array($this->toArray(), $element, true);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function remove($key)
    {
        unset($this->items[$key]);
    }

    public function removeElement($element)
    {
        $key = array_search($this->toArray(), $this->items, true);

        $this->remove($key);
    }

    public function containsKey($key)
    {
        return array_key_exists($key, $this->items);
    }

    public function get($key)
    {
        return $this->createTransfer($this->items[$key]);
    }

    public function getKeys()
    {
        return array_keys($this->items);
    }

    public function getValues()
    {
        return array_values($this->toArray());
    }

    public function set($key, $value)
    {
        $this->items[$key] = $value;
    }

    public function toArray()
    {
        return iterator_to_array($this->getIterator());
    }

    public function first()
    {
        $keys = $this->getKeys();

        return $this->get(reset($keys));
    }

    public function last()
    {
        $keys = $this->getKeys();

        return $this->get(end($keys));
    }

    public function key()
    {
        return key($this->items);
    }

    public function current()
    {
        return $this->createTransfer(current($this->items));
    }

    public function next()
    {
        return $this->createTransfer(next($this->items));
    }

    public function exists(Closure $p): bool
    {
        foreach ($this->getIterator() as $key => $item) {
            if ($p($key, $item)) {
                return true;
            }
        }

        return false;
    }

    public function filter(Closure $p)
    {
        foreach ($this->getIterator() as $item) {
            if ($p($item)) {
                yield $item;
            }
        }
    }

    public function forAll(Closure $p)
    {
        foreach ($this->getIterator() as $key => $item) {
            if (!$p($key, $item)) {
                return false;
            }
        }

        return true;
    }

    public function map(Closure $func)
    {
        foreach ($this->getIterator() as $item) {
            yield $func($item);
        }
    }

    public function partition(Closure $p)
    {
        $matches = $noMatches = array();

        foreach ($this->getIterator() as $key => $element) {
            if ($p($key, $element)) {
                $matches[$key] = $element;
            } else {
                $noMatches[$key] = $element;
            }
        }

        return array($this->createFrom($matches), $this->createFrom($noMatches));
    }

    public function indexOf($element)
    {
        return array_search($element, $this->toArray(), true);
    }

    public function slice($offset, $length = null)
    {
        return array_slice(iterator_to_array($this->getIterator()), $offset, $length, true);
    }

    public function getIterator()
    {
        return call_user_func($this->iterator);
    }

    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
