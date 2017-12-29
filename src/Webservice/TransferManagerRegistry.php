<?php

namespace Vox\Webservice;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;

class TransferManagerRegistry implements ManagerRegistry
{
    /**
     * @var TransferManagerInterface[]
     */
    private $managers;
    
    /**
     * @var WebserviceClientInterface[]
     */
    private $clients;
    
    /**
     * @var string
     */
    private $defaultConnection;
    
    /**
     * @var string
     */
    private $defaultManager;
    
    public function setManager(string $name, TransferManagerInterface $transferManager)
    {
        $this->managers[$name] = $transferManager;
    }
    
    public function setClient(string $name, WebserviceClientInterface $client)
    {
        $this->clients[$name] = $client;
    }
    
    public function setDefaultConnection(string $defaultConnection)
    {
        $this->defaultConnection = $defaultConnection;
    }

    public function setDefaultManager(string $defaultManager)
    {
        $this->defaultManager = $defaultManager;
    }
            
    public function getAliasNamespace($alias): string
    {
        
    }

    public function getConnection($name = null): WebserviceClientInterface
    {
        if (null === $name) {
            return isset($this->defaultConnection) ? $this->clients[$this->defaultConnection] : reset($this->clients);
        }
        
        return $this->clients[$name];
    }

    public function getConnectionNames(): array
    {
        return array_keys($this->clients);
    }

    public function getConnections(): array
    {
        return $this->clients;
    }

    public function getDefaultConnectionName(): string
    {
        return $this->defaultConnection ?? array_keys($this->clients)[0];
    }

    public function getDefaultManagerName(): string
    {
        return $this->defaultManager ?? array_keys($this->managers)[0];
    }

    public function getManager($name = null): TransferManagerInterface
    {
        return $this->managers[$name ?? $this->getDefaultConnectionName()];
    }

    public function getManagerForClass($class)
    {
        return $this->getManager();
    }

    public function getManagerNames(): array
    {
        return array_keys($this->managers);
    }

    public function getManagers(): array
    {
        return $this->managers;
    }

    public function getRepository($persistentObject, $persistentManagerName = null): ObjectRepository
    {
        return $this->getManager($persistentManagerName)->getRepository($persistentObject);
    }

    public function resetManager($name = null): TransferManagerInterface
    {
        $manager = $this->getManager($name);
        $manager->clear();
        
        return $manager;
    }
}
