<?php

namespace Vox\Webservice;

use PHPUnit\Framework\TestCase;

class TransferManagerRegistryTest extends TestCase
{
    public function testRegistry()
    {
        $managers = [
            'one' => $this->createMock(TransferManagerInterface::class),
            'two' => $this->createMock(TransferManagerInterface::class),
            'three' => $this->createMock(TransferManagerInterface::class),
        ];
        
        $connections = [
            'one' => $this->createMock(WebserviceClientInterface::class),
            'two' => $this->createMock(WebserviceClientInterface::class),
            'three' => $this->createMock(WebserviceClientInterface::class),
        ];
        
        $transferManagerRegistry = new TransferManagerRegistry();
        
        foreach ($managers as $name => $transferManager) {
            $transferManagerRegistry->setManager($name, $transferManager);
        }
        
        foreach ($connections as $name => $webserviceClient) {
            $transferManagerRegistry->setClient($name, $webserviceClient);
        }
        
        $this->assertEquals($managers['one'], $transferManagerRegistry->getManager('one'));
        $this->assertEquals($managers['two'], $transferManagerRegistry->getManager('two'));
        $this->assertEquals($managers['one'], $transferManagerRegistry->getManager());
        
        $transferManagerRegistry->setDefaultManager('two');
        $this->assertEquals($managers['two'], $transferManagerRegistry->getManager());
        
        $this->assertEquals($connections['one'], $transferManagerRegistry->getConnection('one'));
        $this->assertEquals($connections['two'], $transferManagerRegistry->getConnection('two'));
        $this->assertEquals($connections['one'], $transferManagerRegistry->getConnection());
        
        $transferManagerRegistry->setDefaultConnection('two');
        $this->assertEquals($connections['two'], $transferManagerRegistry->getConnection());
        
        $this->assertEquals($managers, $transferManagerRegistry->getManagers());
        $this->assertEquals($connections, $transferManagerRegistry->getConnections());
        $this->assertEquals(array_keys($managers), $transferManagerRegistry->getManagerNames());
        $this->assertEquals(array_keys($connections), $transferManagerRegistry->getConnectionNames());
    }
}
