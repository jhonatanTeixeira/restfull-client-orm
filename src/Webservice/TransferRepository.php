<?php

namespace Vox\Webservice;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Vox\Webservice\Exception\WebserviceResponseException;
use Vox\Webservice\Proxy\ProxyFactoryInterface;

/**
 * the transfer repository does the job of requiring data from the webservice client for the correct transfer
 * however this pattern should be more flexible, a future release will fix this
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
final class TransferRepository implements TransferRepositoryInterface
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
     * @param WebserviceClientInterface $webserviceClient
     * @param ObjectStorageInterface $objectStorage
     * @param TransferManagerInterface $transferManager
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
        $transfer = $this->objectStorage->fetchByParams($this->transferName, $id)
            ?? $this->proxyFactory
                ->createProxy($this->webserviceClient->get($this->transferName, $id), $this->transferManager);
        
        if ($transfer && !$this->objectStorage->contains($transfer)) {
            $this->objectStorage->attach($transfer);
        }
        
        return $transfer;
    }

    public function findAll(): Collection
    {
        $collection = $this->webserviceClient->cGet($this->transferName);

        $this->prepareCollection($collection);

        return $collection;
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): Collection
    {
        try {
            $collection = $this->webserviceClient->cGet($this->transferName, $criteria);
        } catch (WebserviceResponseException $exception) {
            if ($exception->getCode() == '404') {
                return new ArrayCollection();
            }

            throw $exception;
        }

        $this->prepareCollection($collection);

        return $collection;
    }

    public function findOneBy(array $criteria)
    {
        try {
            $collection = $this->webserviceClient->cGet($this->transferName, $criteria);
        } catch (WebserviceResponseException $exception) {
            if ($exception->getCode() == '404') {
                return null;
            }

            throw $exception;
        }

        $this->prepareCollection($collection);

        if ($collection->count() > 0) {
            return $collection->first();
        }
    }

    public function findByCriteria(CriteriaInterface $criteria): Collection
    {
        $criteria->withOperationType(CriteriaInterface::OPERATION_TYPE_COLLECTION);

        try {
            $collection = $this->webserviceClient->getByCriteria($criteria, $this->transferName);
        } catch (WebserviceResponseException $ex) {
            if ($ex->getCode() == '404') {
                return new ArrayCollection();
            }

            throw $ex;
        }

        $this->prepareCollection($collection);

        return $collection;
    }

    public function findOneByCriteria(CriteriaInterface $criteria)
    {
        $criteria->withOperationType(CriteriaInterface::OPERATION_TYPE_ITEM);

        try {
            $transfer = $this->webserviceClient->getByCriteria($criteria, $this->transferName);
        } catch (WebserviceResponseException $exception) {
            if ($exception->getCode() == '404') {
                return null;
            }

            throw $exception;
        }

        if ($transfer && !$this->objectStorage->contains($transfer)) {
            $this->objectStorage->attach($transfer);
        }
        
        if ($transfer) {
            $transfer = $this->proxyFactory->createProxy($transfer, $this->transferManager);
        }

        return $transfer;
    }

    private function prepareCollection(TransferCollection $collection)
    {
        $collection->setObjectStorage($this->objectStorage)
            ->setTransferManager($this->transferManager)
            ->setProxyFactory($this->proxyFactory);
    }

    public function getClassName(): string
    {
        return $this->transferName;
    }
}
