<?php

namespace Vox\Webservice;

use Doctrine\Common\Persistence\ObjectRepository;
use Metadata\MetadataFactoryInterface;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\Proxy\ProxyFactory;
use Vox\Webservice\Proxy\ProxyFactoryInterface;

class TransferManager implements TransferManagerInterface
{
    /**
     * @var UnityOfWorkInterface
     */
    private $unityOfWork;
    
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;
    
    /**
     * @var WebserviceClientInterface
     */
    private $webserviceClient;
    
    /**
     * @var TransferPersisterInterface
     */
    private $transferPersister;
    
    /**
     * @var ProxyFactoryInterface
     */
    private $proxyFactory;
    
    public function __construct(
        MetadataFactoryInterface $metadataFactory,
        WebserviceClientInterface $webserviceClient
    ) {
        $this->metadataFactory   = $metadataFactory;
        $this->webserviceClient  = $webserviceClient;
        $this->proxyFactory      = new ProxyFactory();
        
        $this->clear();
    }
    
    /**
     * @todo injection of these objects
     * 
     * @param type $objectName
     */
    public function clear($objectName = null)
    {
        $this->unityOfWork       = new UnityOfWork($this->metadataFactory);
        $this->transferPersister = new TransferPersister(
            $this->metadataFactory,
            $this->unityOfWork,
            $this->webserviceClient
        );
    }

    public function contains($object): bool
    {
        return $this->unityOfWork->contains($object);
    }

    public function detach($object)
    {
        $this->unityOfWork->detach($object);
    }

    public function find($className, $id)
    {
        return $this->getRepository($className)->find($id);
    }

    public function flush()
    {
        foreach ($this->unityOfWork as $transfer) {
            $this->transferPersister->save($transfer);
        }
    }

    public function getClassMetadata($className): TransferMetadata
    {
        if(is_object($className)) {
            $className = get_class($className);
        }
        
        return $this->metadataFactory->getMetadataForClass($className);
    }

    public function getMetadataFactory(): MetadataFactoryInterface
    {
        return $this->metadataFactory;
    }

    public function getRepository($className): ObjectRepository
    {
        return new TransferRepository(
            $className,
            $this->webserviceClient,
            $this->unityOfWork,
            $this,
            $this->proxyFactory
        );
    }

    public function initializeObject($obj)
    {
        
    }

    public function merge($object)
    {
        
    }

    public function persist($object)
    {
        $this->unityOfWork->attach($object);
    }

    public function refresh($object)
    {
        
    }

    public function remove($object)
    {
        $this->unityOfWork->remove($object);
    }
}
