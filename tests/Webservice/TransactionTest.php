<?php

namespace Vox\Webservice;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Vox\Webservice\Factory\TransferManagerBuilder;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Mapping\Resource;

class TransactionTest extends TestCase
{
    /**
     * @expectedException Vox\Webservice\Exception\WebserviceResponseException
     */
    public function testShouldRollbackData()
    {
        $clientRegistry = new ClientRegistry();
        $guzzleClient = $this->createMock(Client::class);
        $clientRegistry->set('foo', $guzzleClient);
        
         $guzzleClient->expects($this->exactly(9))
            ->method('request')
            ->withConsecutive(
                ['GET', '/foo/1', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/foo/2', ['headers' => ['Content-Type' => 'application/json']]],
                ['PUT', '/foo/1', ['json' => [
                    'id' => 1,
                    'name' => 'foo2'
                ]]],
                ['DELETE', '/foo/2'],
                ['POST', '/foo', ['json' => [
                    'id' => null, 
                    'name' => 'baz',
                ]]],
                ['POST', '/foo', ['json' => [
                    'id' => null, 
                    'name' => 'baz2',
                ]]],
                ['PUT', '/foo/1', ['json' => [
                    'id' => 1,
                    'name' => 'foo'
                ]]],
                ['POST', '/foo', ['json' => [
                    'id' => 2, 
                    'name' => 'bar',
                ]]],
                ['DELETE', '/foo/3']
            )
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode(['id' => 1, 'name' => 'foo'])),
                new Response(200, [], json_encode(['id' => 2, 'name' => 'bar'])),
                new Response(200, [], json_encode(['id' => 1])),
                new Response(200, []),
                new Response(200, [], json_encode(['id' => 3])),
                new Response(400, []),
                new Response(200, [], json_encode(['id' => 1])),
                new Response(200, [], json_encode(['id' => 2])),
                new Response(200, [])
            )
        ;
         
        $builder = new TransferManagerBuilder();
        $builder->withClientRegistry($clientRegistry)
            ->isTransactional()
            ->withMetadataDriver('annotation');
        
        $transferManager = $builder->createTransferManager();
        
        $foo = $transferManager->find(RollbackDataStub::class, 1);
        $bar = $transferManager->find(RollbackDataStub::class, 2);
        
        $foo->setName('foo2');
        
        $baz = new RollbackDataStub();
        $baz->setName('baz');
        
        $baz2 = new RollbackDataStub();
        $baz2->setName('baz2');
        
        $transferManager->persist($baz);
        $transferManager->persist($baz2);
        $transferManager->remove($bar);
        
        $transferManager->flush();
    }
}

/**
 * @Resource(client="foo", route="/foo")
 */
class RollbackDataStub
{
    /**
     * @Id
     *
     * @var int
     */
    private $id;
    
    /**
     * @var string
     */
    private $name;
    
    public function setName($name)
    {
        $this->name = $name;
        
        return $this;
    }
}
