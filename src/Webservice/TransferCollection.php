<?php

namespace Vox\Webservice;

use Doctrine\Common\Collections\ArrayCollection;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Vox\Webservice\Proxy\ProxyFactoryInterface;

/**
 * Transfer collection is used as a way to keep the objects inside the unity of work and proxyed
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class TransferCollection extends ArrayCollection
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
    
    public function __construct(string $transferName, DenormalizerInterface $denormalizer, ResponseInterface $response)
    {
        $response = json_decode($response->getBody()->getContents() ?: '[]', true);
        
        parent::__construct($response);
        
        $this->transferName = $transferName;
        $this->denormalizer = $denormalizer;
    }
    
    public function current()
    {
        $current = parent::current();
        
        if (!is_object($current)) {
            $current = $this->offsetGet($this->key());
        }
        
        return $current;
    }
    
    public function offsetGet($offset)
    {
        $data = parent::offsetGet($offset);
        
        if (!is_object($data)) {
            $data = $this->denormalizer->denormalize($data, $this->transferName);
            
            if ($this->proxyFactory && $this->transferManager) {
                $data = $this->proxyFactory->createProxy($data, $this->transferManager);
            }
            
            $this->offsetSet($offset, $data);
        }
        
        if (isset($this->objectStorage) && !$this->objectStorage->contains($data)) {
            $this->objectStorage->attach($data);
        }
        
        return $data;
    }
    
    public function first()
    {
        $keys = $this->getKeys();
        $key  = reset($keys);
        
        return $this->offsetGet($key);
    }
    
    public function last()
    {
        $keys = $this->getKeys();
        $key  = end($keys);
        
        return $this->offsetGet($key);
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
}
