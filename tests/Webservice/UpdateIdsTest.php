<?php

namespace Vox\Webservice;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Vox\Data\Mapping\Exclude;
use Vox\Webservice\Factory\TransferManagerBuilder;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Mapping\Resource;

class UpdateIdsTest extends TestCase
{
    public function testUpdateIds()
    {
        $managerBuilder = new TransferManagerBuilder();
        $clientRegistry = new ClientRegistry();
        $guzzleClient   = $this->createMock(Client::class);
        
        $guzzleClient->expects($this->exactly(2))
            ->method('request')
            ->withConsecutive(
                ['GET', '/test/1', ['headers' => ['Content-Type' => 'application/json']]],
                ['POST', '/test', ['json' => [
                    'id' => null,
                    'data' => null,
                    'related' => 1
                ]]]
            )
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode(['id' => 1, 'data' => [1,2], 'related' => null])),
                new Response(201, [], json_encode(['id' => 2]))
            );
        
        $managerBuilder->withClientRegistry((new ClientRegistry)->set('test', $guzzleClient));
        
        $tranfserManager = $managerBuilder->createTransferManager();
        
        $relation = $tranfserManager->find(SimpleRelation::class, 1);
        
        $simple = new SimpleRelation();
        $simple->setRelatedTransfer($relation);
        
        $tranfserManager->persist($simple);
        
        $tranfserManager->flush();
    }
}

/**
 * @Resource(client="test", route="/test")
 */
class SimpleRelation
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
    
    /**
     * @var int
     */
    private $related;
    
    /**
     * @BelongsTo(foreignField="related")
     * @Exclude
     * 
     * @var SimpleRelation
     */
    private $relatedTransfer;
    
    public function getId()
    {
        return $this->id;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }
    
    public function getRelated()
    {
        return $this->related;
    }

    public function getRelatedTransfer(): SimpleRelation
    {
        return $this->relatedTransfer;
    }

    public function setRelated($related)
    {
        $this->related = $related;
        return $this;
    }

    public function setRelatedTransfer(SimpleRelation $relatedTransfer)
    {
        $this->relatedTransfer = $relatedTransfer;
        return $this;
    }
}
