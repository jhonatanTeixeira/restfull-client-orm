<?php

namespace Vox\Webservice;

use Metadata\MetadataFactoryInterface;
use SplObjectStorage;

/**
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class Transaction implements TransactionInterface
{
    use MetadataTrait;
    
    /**
     * @var SplObjectStorage
     */
    private $storage;
    
    /**
     * @var TransferManagerInterface
     */
    private $transferManager;
    
    /**
     * @var WebserviceClientInterface
     */
    private $webServiceClient;
    
    private $states = [
        'created' => 'delete',
        'updated' => 'put',
        'deleted' => 'post',
    ];
    
    public function __construct(
        TransferManagerInterface $transferManager, 
        WebserviceClientInterface $webServiceClient,
        MetadataFactoryInterface $metadataFactory
    ) {
        $this->storage          = new SplObjectStorage();
        $this->transferManager  = $transferManager;
        $this->webServiceClient = $webServiceClient;
        $this->metadataFactory  = $metadataFactory;
    }

    public function addCreated($object)
    {
        $this->storage[$object] = $this->states['created'];
    }
    
    public function addUpdated($object)
    {
        $this->storage[$object] = $this->states['updated'];
    }
    
    public function addDeleted($object)
    {
        $this->storage[$object] = $this->states['deleted'];
    }
    
    public function rollback()
    {
        foreach ($this->storage as $object) {
            $state = $this->storage->getInfo();
            $args  = [$object];
            
            if ($state == 'delete') {
                $args= [get_class($object), $this->getIdValue($object)];
            }
            
            call_user_func([$this->webServiceClient, $state], ...$args);
        }
    }
}
