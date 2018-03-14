<?php

namespace Vox\Webservice;

use Doctrine\Common\Collections\Collection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Vox\Webservice\Factory\TransferManagerBuilder;
use Vox\Webservice\Mapping\HasMany;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Mapping\Resource;

class TransferManagerIriRelationshipsTest extends TestCase
{
    public function testShouldFetchHasManyIri()
    {
        $clientRegistry = new ClientRegistry();
        $guzzleClient = $this->createMock(Client::class);
        $clientRegistry->set('foo', $guzzleClient);
        
         $guzzleClient->expects($this->exactly(3))
            ->method('request')
            ->withConsecutive(
                ['GET', '/foo/1', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/foo/2', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/foo/3', ['headers' => ['Content-Type' => 'application/json']]]
            )
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode(['id' => 1, 'related' => ['/foo/2', '/foo/3']])),
                new Response(200, [], json_encode(['id' => 2])),
                new Response(200, [], json_encode(['id' => 3]))
            )
        ;
         
        $builder = new TransferManagerBuilder();
        $builder->withClientRegistry($clientRegistry);
        
        $transferManager = $builder->createTransferManager();
        
        $iri = $transferManager->find(IriRelated::class, 1);
        
        $collection = $iri->getRelatedTransfers();
        
        $this->assertInstanceOf(IriRelated::class, $collection->first());
        $this->assertEquals($collection->first()->getId(), 2);
        $this->assertEquals($collection->last()->getId(), 3);
    }
    
    public function testShouldFetchHasManyIriYml()
    {
        $clientRegistry = new ClientRegistry();
        $guzzleClient = $this->createMock(Client::class);
        $clientRegistry->set('foo', $guzzleClient);
        
         $guzzleClient->expects($this->exactly(3))
            ->method('request')
            ->withConsecutive(
                ['GET', '/foo/1', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/foo/2', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/foo/3', ['headers' => ['Content-Type' => 'application/json']]]
            )
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode(['id' => 1, 'related' => ['/foo/2', '/foo/3']])),
                new Response(200, [], json_encode(['id' => 2])),
                new Response(200, [], json_encode(['id' => 3]))
            )
        ;
         
        $builder = new TransferManagerBuilder();
        $builder->withClientRegistry($clientRegistry)
            ->withMetadataDriver('yaml')
            ->withMetadataPath(__DIR__ . '/../fixtures/metadata');
        
        $transferManager = $builder->createTransferManager();
        
        $iri = $transferManager->find(IriRelatedYml::class, 1);
        
        $collection = $iri->getRelatedTransfers();
        
        $this->assertInstanceOf(IriRelatedYml::class, $collection->first());
        $this->assertEquals($collection->first()->getId(), 2);
        $this->assertEquals($collection->last()->getId(), 3);
    }
}

/**
 * @Resource(client="foo", route="/foo")
 */
class IriRelated
{
    /**
     * @Id
     *
     * @var int
     */
    private $id;
    
    /**
     * @var array
     */
    private $related;
    
    /**
     * @HasMany(iriCollectionField="related")
     * 
     * @var IriRelated[]
     */
    private $relatedTransfers;
    
    function getId()
    {
        return $this->id;
    }

    function getRelated()
    {
        return $this->related;
    }

    function getRelatedTransfers(): Collection
    {
        return $this->relatedTransfers;
    }

    function setId($id)
    {
        $this->id = $id;
    }

    function setRelated(array $related)
    {
        $this->related = $related;
    }

    function setRelatedTransfers($relatedTransfers)
    {
        $this->relatedTransfers = $relatedTransfers;
    }
}

class IriRelatedYml
{
    /**
     * @var int
     */
    private $id;
    
    /**
     * @var array
     */
    private $related;
    
    /**
     * @var IriRelatedYml[]
     */
    private $relatedTransfers;
    
    function getId()
    {
        return $this->id;
    }

    function getRelated()
    {
        return $this->related;
    }

    function getRelatedTransfers(): Collection
    {
        return $this->relatedTransfers;
    }

    function setId($id)
    {
        $this->id = $id;
    }

    function setRelated($related)
    {
        $this->related = $related;
    }

    function setRelatedTransfers($relatedTransfers)
    {
        $this->relatedTransfers = $relatedTransfers;
    }
}
