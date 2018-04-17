<?php

namespace Vox\Webservice;

use PHPUnit\Framework\TestCase;
use Vox\Webservice\Event\PersistenceEvents;
use Vox\Webservice\Factory\TransferManagerBuilder;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Mapping\Resource;

class PersistenceEventsTest extends TestCase
{
    public function testShouldCallAllEvents()
    {
        $managerBuilder = new TransferManagerBuilder();
        $webserviceMock = $this->createMock(WebserviceClient::class);
        
        $webserviceMock->expects($this->any())
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                new SimpleStub(1),
                new SimpleStub(2, [1, 2])
            );
        
        $eventDispatcher = new EventDispatcher();
        
        $listenerMock = $this->createMock(ListenerStub::class);
        $listenerMock->expects($this->once())
            ->method(PersistenceEvents::PRE_FLUSH);
        $listenerMock->expects($this->once())
            ->method(PersistenceEvents::PRE_PERSIST);
        $listenerMock->expects($this->once())
            ->method(PersistenceEvents::PRE_UPDATE);
        $listenerMock->expects($this->once())
            ->method(PersistenceEvents::PRE_REMOVE);
        $listenerMock->expects($this->once())
            ->method(PersistenceEvents::POST_FLUSH);
        
        $eventDispatcher
            ->addEventListener(
                [
                    PersistenceEvents::PRE_FLUSH, 
                    PersistenceEvents::PRE_PERSIST, 
                    PersistenceEvents::PRE_UPDATE, 
                    PersistenceEvents::PRE_REMOVE, 
                    PersistenceEvents::POST_FLUSH, 
                ], 
                $listenerMock
            );
        
        $managerBuilder->withWebserviceClient($webserviceMock)
            ->withClientRegistry($this->createMock(ClientRegistry::class))
            ->withEventDispatcher($eventDispatcher);
        
        $tranfserManager = $managerBuilder->createTransferManager();
        
        $one  = $tranfserManager->find(SimpleStub::class, 1);
        $two  = $tranfserManager->find(SimpleStub::class, 2);
        $tree = new SimpleStub(null);
        $one->setData([3, 5]);
        
        $tranfserManager->persist($tree);
        $tranfserManager->remove($two);
        
        $tranfserManager->flush();
    }
}

interface ListenerStub
{
    public function prePersist();
    public function postPersist();
    public function preUpdate();
    public function preRemove();
    public function postUpdate();
    public function preFlush();
    public function postFlush();
}

/**
 * @Resource(client="test", route="/test")
 */
class SimpleStub
{
    /**
     * @Id()
     * @var int
     */
    private $id;
    
    /**
     * @var array
     */
    private $data;
    
    public function __construct($id, array $data = [])
    {
        $this->id   = $id;
        $this->data = $data;
    }

    public function getId()
    {
        return $this->id;
    }
        
    public function setData(array $data)
    {
        $this->data = $data;
        
        return $this;
    }
}