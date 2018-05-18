<?php

namespace Vox\Webservice\Event;

use Throwable;
use Vox\Webservice\Exception\RollbackException;
use Vox\Webservice\TransactionInterface;

class TransactionEventListener
{
    /**
     * @var TransactionInterface
     */
    private $transaction;
    
    public function __construct(TransactionInterface $transaction)
    {
        $this->transaction = $transaction;
    }
    
    public function postPersist(LifecycleEvent $event)
    {
        $this->transaction->addCreated($event->getObject());
    }
    
    public function preUpdate(LifecycleEvent $event)
    {
        $object   = $event->getObject();
        $original = $event->getObjectManager()->getUnitOfWork()->getOriginalObject($object);
        $this->transaction->addUpdated($original);
    }
    
    public function postRemove(LifecycleEvent $event)
    {
        $this->transaction->addDeleted($event->getObject());
    }
    
    public function onException()
    {
        try {
            $this->transaction->rollback();
        } catch (Throwable $ex) {
            throw new RollbackException($ex);
        }
    }
}
