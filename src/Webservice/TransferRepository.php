<?php

namespace Vox\Webservice;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectRepository;

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
    
    public function __construct(
        string $transferName, 
        WebserviceClientInterface $webserviceClient, 
        ObjectStorageInterface $objectStorage
    ) {
        $this->transferName     = $transferName;
        $this->webserviceClient = $webserviceClient;
        $this->objectStorage    = $objectStorage;
    }
    
    public function find($id)
    {
        $transfer = $this->webserviceClient->get($this->transferName, $id);
        
        if ($transfer && !$this->objectStorage->contains($transfer)) {
            $this->objectStorage->attach($transfer);
        }
        
        return $transfer;
    }

    public function findAll(): ArrayCollection
    {
        $collection = $this->webserviceClient->cGet($this->transferName);
        
        $collection->setObjectStorage($this->objectStorage);
        
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
