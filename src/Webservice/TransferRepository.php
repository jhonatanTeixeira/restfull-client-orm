<?php

namespace Vox\Webservice;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectRepository;
use Vox\Webservice\Proxy\ProxyFactoryInterface;

/**
 * the transfer repository does the job of requiring data from the webservice client for the correct transfer
 * however this pattern should be more flexible, a future release will fix this
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
final class TransferRepository implements ObjectRepository
{
    private $transferName;
    
    /**
     * @var WebserviceClientInterface
     */
    private $webserviceClient;
    
    /**
     * @var ObjectStorageInterface
     */
    private $objectStorage;
    
    /**
     * @var TransferManagerInterface
     */
    private $transferManager;
    
    /**
     * @var ProxyFactoryInterface
     */
    private $proxyFactory;
    
    /**
     * @param string $transferName
     * @param \Vox\Webservice\WebserviceClientInterface $webserviceClient
     * @param \Vox\Webservice\ObjectStorageInterface $objectStorage
     * @param \Vox\Webservice\TransferManagerInterface $transferManager
     * @param ProxyFactoryInterface $proxyFactory
     */
    public function __construct(
        string $transferName, 
        WebserviceClientInterface $webserviceClient, 
        ObjectStorageInterface $objectStorage,
        TransferManagerInterface $transferManager,
        ProxyFactoryInterface $proxyFactory
    ) {
        $this->transferName     = $transferName;
        $this->webserviceClient = $webserviceClient;
        $this->objectStorage    = $objectStorage;
        $this->transferManager  = $transferManager;
        $this->proxyFactory     = $proxyFactory;
    }
    
    public function find($id)
    {
        $transfer = $this->proxyFactory
            ->createProxy($this->webserviceClient->get($this->transferName, $id), $this->transferManager);
        
        if ($transfer && !$this->objectStorage->contains($transfer)) {
            $this->objectStorage->attach($transfer);
        }
        
        return $transfer;
    }

    public function findAll(): ArrayCollection
    {
        $collection = $this->webserviceClient->cGet($this->transferName);
        
        $collection->setObjectStorage($this->objectStorage)
            ->setTransferManager($this->transferManager)
            ->setProxyFactory($this->proxyFactory);
        
        return $collection;
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): array
    {
        $collection = $this->webserviceClient->cGet($this->transferName, $criteria);
        
        $collection->setObjectStorage($this->objectStorage);
        
        return $collection;
    }

    public function findOneBy(array $criteria)
    {
        $collection = $this->webserviceClient->cGet($this->transferName, $criteria);
        
        $collection->setObjectStorage($this->objectStorage);
        
        if ($collection->count() > 0) {
            return $collection->first();
        }
    }

    public function getClassName(): string
    {
        return $this->transferName;
    }
}
