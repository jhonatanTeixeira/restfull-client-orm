<?php

namespace Vox\Webservice;

use Metadata\MetadataFactoryInterface;
use Vox\Webservice\Event\DispatchEventTrait;
use Vox\Webservice\Event\LifecycleEvent;
use Vox\Webservice\Event\ManagerEvent;
use Vox\Webservice\Event\PersistenceEvents;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\Proxy\ProxyFactory;
use Vox\Webservice\Proxy\ProxyFactoryInterface;

/**
 * This is the transfer manager, it is the best way for you to control the state of your transfers.
 * this class is a facade to simplify the use of the unity of work and the repository pattern contained
 * on this project
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class TransferManager implements TransferManagerInterface
{
    use DispatchEventTrait;
    
    /**
     * @var UnityOfWorkInterface
     */
    private $unitOfWork;
    
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
    
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;
    
    public function __construct(
        MetadataFactoryInterface $metadataFactory,
        WebserviceClientInterface $webserviceClient,
        ProxyFactoryInterface $proxyFactory = null,
        EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->metadataFactory  = $metadataFactory;
        $this->webserviceClient = $webserviceClient;
        $this->proxyFactory     = $proxyFactory ?? new ProxyFactory();
        $this->eventDispatcher  = $eventDispatcher;
        
        $this->clear();
    }
    
    /**
     * @todo injection of these objects
     * 
     * @param type $objectName
     */
    public function clear($objectName = null)
    {
        $this->unitOfWork       = new UnitOfWork($this->metadataFactory);
        $this->transferPersister = new TransferPersister(
            $this->metadataFactory,
            $this->unitOfWork,
            $this->webserviceClient,
            $this,
            $this->eventDispatcher
        );
    }

    public function contains($object): bool
    {
        return $this->unitOfWork->contains($object);
    }

    public function detach($object)
    {
        $this->unitOfWork->detach($object);
    }

    public function find($className, $id)
    {
        return $this->getRepository($className)->find($id);
    }

    public function flush()
    {
        $event = new ManagerEvent($this);
        
        $this->dispatchEvent(PersistenceEvents::PRE_FLUSH, $event);
        
        foreach ($this->unitOfWork as $transfer) {
            $this->transferPersister->save($transfer);
        }
        
        $this->dispatchEvent(PersistenceEvents::POST_FLUSH, $event);
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

    public function getRepository($className): TransferRepositoryInterface
    {
        return new TransferRepository(
            $className,
            $this->webserviceClient,
            $this->unitOfWork,
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
        $this->dispatchEvent(PersistenceEvents::PRE_PERSIST, new LifecycleEvent($object, $this));
        $this->unitOfWork->attach($object);
    }

    public function refresh($object)
    {
        
    }

    public function remove($object)
    {
        $this->unitOfWork->remove($object);
    }
    
    public function getUnitOfWork(): UnitOfWorkInterface
    {
        return $this->unitOfWork;
    }
}
