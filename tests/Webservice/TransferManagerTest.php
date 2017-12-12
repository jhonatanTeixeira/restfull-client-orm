<?php

namespace Vox\Webservice;

use Doctrine\Common\Annotations\AnnotationReader;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Vox\Data\Mapping\Bindings;
use Vox\Data\ObjectHydrator;
use Vox\Metadata\Driver\AnnotationDriver;
use Vox\Serializer\Denormalizer;
use Vox\Serializer\Normalizer;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Mapping\Resource;
use Vox\Webservice\Metadata\TransferMetadata;

class TransferManagerTest extends TestCase
{
    private $webserviceClient;
    
    /**
     * @var Client
     */
    private $client;
    
    /**
     * @var Serializer
     */
    private $serializer;
    
    /**
     * @var MockHandler
     */
    private $mockHandler;
    
    /**
     * @var TransferManager
     */
    private $transferManager;
    
    private $metadataFactory;
    
    private $registry;
    
    protected function setUp()
    {
        $this->mockHandler = new MockHandler();
        
        $this->client = $client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        
        $this->registry = $registry = new ClientRegistry();
        $registry->set('some_client', $client);
        
        $this->metadataFactory = $metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), TransferMetadata::class));
        $this->serializer      = $serializer      = new Serializer(
            [
                new Normalizer($metadataFactory), 
                new Denormalizer(new ObjectHydrator($metadataFactory))
            ], 
            [
                new JsonEncoder()
            ]
        );
        
        $webserviceClient = $this->getMockBuilder(WebserviceClient::class)
            ->setConstructorArgs([$registry, $metadataFactory, $serializer, $serializer])
            ->setMethods(['post', 'put', 'delete'])
            ->getMock();
        
        $this->webserviceClient = $webserviceClient;
        $this->transferManager  = new TransferManager($metadataFactory, $webserviceClient);
    }
    
    public function testShouldFindAllAndUpdateOne()
    {
        $this->mockHandler->append(
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    [
                        'id' => 1,
                        'nome' => 'fulano',
                        'email' => 'fulano@fulano.com',
                    ],
                    [
                        'id' => 2,
                        'nome' => 'fulano',
                        'email' => 'fulano@fulano.com',
                    ],
                    [
                        'id' => 3,
                        'nome' => 'fulano',
                        'email' => 'fulano@fulano.com',
                    ]
                ])
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(
                    [
                        'id' => 2,
                        'nome' => 'fulano',
                        'email' => 'fulano@fulano.com',
                    ]
                )
            )
        );
        
        
        $transfers = $this->transferManager->getRepository(TransferStub::class)->findAll();
        
        $transfer = $transfers->first();
        
        $transfer->setNome('fulaner');
        
        $this->webserviceClient->expects($this->once())
            ->method('put')
            ->with($transfer)
            ->willReturn(
                new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1]))
            );
        
        $this->transferManager->flush();
    }
    
    public function testShouldFindAllAndUpdateTwo()
    {
        $this->mockHandler->append(
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    [
                        'id' => 1,
                        'nome' => 'fulano',
                        'email' => 'fulano@fulano.com',
                    ],
                    [
                        'id' => 2,
                        'nome' => 'fulano',
                        'email' => 'fulano@fulano.com',
                    ],
                    [
                        'id' => 3,
                        'nome' => 'fulano',
                        'email' => 'fulano@fulano.com',
                    ]
                ])
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(
                    [
                        'id' => 2,
                        'nome' => 'fulano',
                        'email' => 'fulano@fulano.com',
                    ]
                )
            )
        );
        
        
        $transfers = $this->transferManager->getRepository(TransferStub::class)->findAll();
        
        $transfer1 = $transfers[0];
        $transfer1->setNome('fulaner');
        
        $transfer2 = $transfers[1];
        $transfer2->setNome('fulaner2');
        
        $this->webserviceClient->expects($this->exactly(2))
            ->method('put')
            ->withConsecutive($transfer1, $transfer2)
            ->willReturn(
                new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 1])),
                new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 2]))
            );
        
        $this->transferManager->flush();
    }
    
    public function testShouldFindByIdAndUpdate()
    {
        $this->mockHandler->append(
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(
                    [
                        'id' => 2,
                        'nome' => 'fulano',
                        'email' => 'fulano@fulano.com',
                    ]
                )
            )
        );
        
        
        $transfer = $this->transferManager->getRepository(TransferStub::class)->find(2);
        
        $transfer->setNome('fulaner1');
        
        $this->webserviceClient->expects($this->once())
            ->method('put')
            ->with($transfer)
            ->willReturn(
                new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 2]))
            );
        
        $this->transferManager->flush();
    }
    
    public function testShouldCreate()
    {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 4])));
        
        $this->transferManager = new TransferManager(
            $this->metadataFactory,
            new WebserviceClient($this->registry, $this->metadataFactory, $this->serializer, $this->serializer)
        );
        
        $transfer = new TransferStub();
        $transfer->setNome('fulmaner');
        
        $this->transferManager->persist($transfer);
        
        $this->transferManager->flush();
        
        $this->assertEquals(4, $transfer->getId());
        $this->assertEquals('fulmaner', $transfer->getNome());
    }
    
    public function testShouldDelete()
    {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 4])));
        
        $transfer = $this->transferManager->getRepository(TransferStub::class)->find(4);
        
        $this->webserviceClient->expects($this->once())
            ->method('delete')
            ->with(get_class($transfer), 4)
            ->willReturn(
                new Response(200)
            );
        
        $this->transferManager->remove($transfer);
        
        $this->transferManager->flush();
    }
    
    public function testShouldGetRelations()
    {
        $webserviceClient = $this->getMockBuilder(WebserviceClient::class)
            ->setConstructorArgs([$this->registry, $this->metadataFactory, $this->serializer, $this->serializer])
            ->setMethods(['get'])
            ->getMock();
        
        $webserviceClient->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive([RelatedStub::class, 1], [RelationStub::class, 10])
            ->willReturnCallback(function ($transferName) {
                $contents = $this->client->get('/abc')->getBody()->getContents();
                return $this->serializer->denormalize(json_decode($contents, true), $transferName);
            });
        
        $this->transferManager = new TransferManager($this->metadataFactory, $webserviceClient);
        
        $this->mockHandler->append(
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'id' => 1,
                    'relationId' => 10
                ])
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'id' => 10,
                    'name' => 'relation'
                ])
            )
        );
        
        $related  = $this->transferManager->find(RelatedStub::class, 1);
        $relation = $related->getRelated();
        
        $this->assertEquals('relation', $relation->getName());
    }
    
    public function testShouldPostRelated()
    {
        $related = new RelatedStub();
        $relation = new RelationStub();
        $relation->setName('ssss');
        $related->setRelated($relation);
        
        $this->webserviceClient->expects($this->exactly(2))
            ->method('post')
            ->withConsecutive([$relation], [$related])
            ->willReturn(null);
        
        $this->transferManager->persist($related);
        
        $this->transferManager->flush();
    }
}

/**
 * @Resource(client="some_client", route="/test")
 */
class TransferStub
{
    /**
     * @Id
     */
    private $id;

    /**
     * @Bindings(source="nome")
     */
    private $nome;
    
    /**
     * @Bindings(source="email")
     */
    private $email;
    
    /**
     * @Bindings(source="site")
     */
    private $site;
    
    public function getId()
    {
        return $this->id;
    }
        
    public function getNome()
    {
        return $this->nome;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getSite()
    {
        return $this->site;
    }
    
    public function setNome($nome)
    {
        $this->nome = $nome;
        
        return $this;
    }
}

/**
 * @Resource(client="some_client", route="/related")
 */
class RelatedStub
{
    /**
     * @Id
     *
     * @var int
     */
    private $id;
    
    /**
     * @var int
     */
    private $relationId;
    
    /**
     * @BelongsTo(foreignField = "relationId")
     * 
     * @var RelationStub
     */
    private $related;
    
    public function getRelationId()
    {
        return $this->relationId;
    }

    public function getRelated(): RelationStub
    {
        return $this->related;
    }
    
    public function setRelated(RelationStub $related)
    {
        $this->related = $related;
    }
}

/**
 * @Resource(client="some_client", route="/relation")
 */
class RelationStub
{
    /**
     * @Id
     *
     * @var int
     */
    private $id;
    
    private $name;
    
    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }
    
    public function setName($name)
    {
        $this->name = $name;
    }
}