<?php

namespace Vox\Webservice;

use DateTime;
use Doctrine\Common\Annotations\AnnotationReader;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Vox\Data\Mapping\Bindings;
use Vox\Data\Mapping\Discriminator;
use Vox\Data\ObjectHydrator;
use Vox\Metadata\Driver\AnnotationDriver;
use Vox\Metadata\Driver\YmlDriver;
use Vox\Serializer\Denormalizer;
use Vox\Serializer\Normalizer;
use Vox\Webservice\Exception\WebserviceResponseException;
use Vox\Webservice\Mapping\Resource;
use Vox\Webservice\Metadata\TransferMetadata;

class WebserviceClientPHP74Test extends TestCase
{
    /**
     * @var WebserviceClient
     */
    private $webserviceClient;
    
    private $serializer;
    
    private $mockHandler;
    
    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        
        $client = new Client(['handler' => $this->mockHandler]);
        
        $registry = new ClientRegistry();
        $registry->set('some_client', $client);
        
        $metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), TransferMetadata::class));
        $serializer = new Serializer(
            [
                new Normalizer($metadataFactory), 
                new Denormalizer(new ObjectHydrator($metadataFactory))
            ], 
            [
                new JsonEncoder()
            ]
        );
        
        $webserviceClient = new WebserviceClient($registry, $metadataFactory, $serializer, $serializer);
        
        $this->webserviceClient = $webserviceClient;
        $this->serializer       = $serializer;
    }
    
    public function testShouldConsumeRequest()
    {
        $this->mockHandler->append(
            new Response(
                200, 
                ['Content-Type' => 'application/json'], 
                json_encode([
                    'ds_some' => 'data',
                    'co_other' => [
                        'otherValue' => 10
                    ]
                ])
            )
        );
        
        $webserviceClient = $this->webserviceClient;
        
        $result = $webserviceClient->get(SomeStub74::class, 1);
        
        $this->assertInstanceOf(SomeStub74::class, $result);
        $this->assertEquals('data', $result->some);
        $this->assertEquals(10, $result->other->getOtherValue());
    }
    
    public function testShouldConsumeCollection()
    {
        $this->mockHandler->append(
            new Response(
                200, 
                ['Content-Type' => 'application/json'], 
                json_encode([
                    [
                        'ds_some' => 'data',
                        'co_other' => [
                            'otherValue' => 10
                        ]
                    ],
                    [
                        'ds_some' => 'data2',
                        'co_other' => [
                            'otherValue' => 11,
                            'type' => 'extra'
                        ]
                    ],
                ])
            )
        );
        
        $webserviceClient = $this->webserviceClient;
        
        $result = $webserviceClient->cGet(SomeStub74::class);
        
        $this->assertCount(2, $result);
        
        $this->assertInstanceOf(SomeStub74::class, $result[0]);
        $this->assertInstanceOf(SomeStub74::class, $result[1]);
        $this->assertInstanceOf(ExtraStub74::class, $result[1]->other);
        $this->assertEquals('data', $result[0]->some);
        $this->assertEquals(10, $result[0]->other->getOtherValue());
        $this->assertEquals('data2', $result[1]->some);
        $this->assertEquals(11, $result[1]->other->getOtherValue());
    }
    
    public function testShouldDiscriminate()
    {
        $this->mockHandler->append(
            new Response(
                200, 
                ['Content-Type' => 'application/json'], 
                json_encode([
                    'ds_some' => 'data',
                    'co_other' => [
                        'otherValue' => 10,
                        'extra_value' => 11,
                        'type' => 'extra'
                    ]
                ])
            )
        );
        
        $webserviceClient = $this->webserviceClient;
        
        $result = $webserviceClient->get(SomeStub74::class, 1);
        
        $this->assertInstanceOf(SomeStub74::class, $result);
        $this->assertInstanceOf(ExtraStub74::class, $result->other);
        $this->assertEquals(10, $result->other->getOtherValue());
        $this->assertEquals(11, $result->other->getExtraValue());
    }

    public function testShouldConvertValues()
    {
        $this->mockHandler->append(
            new Response(
                200, 
                ['Content-Type' => 'application/json'], 
                json_encode([
                    'ds_some' => 'data',
                    'co_other' => [
                        'otherValue' => 10,
                        'extra_value' => 11,
                        'stubs' => [
                            [
                                'otherValue' => 10,
                                'extra_value' => 111,
                                'date' => '1983-12-20',
                                'type' => 'extra'
                            ],
                            [
                                'otherValue' => 10,
                                'extra_value' => 11,
                                'type' => 'some'
                            ],
                        ],
                        'type' => 'extra'
                    ]
                ])
            )
        );
        
        $webserviceClient = $this->webserviceClient;
        
        $result = $webserviceClient->get(SomeStub74::class, 1);
        
        $this->assertInstanceOf(SomeStub74::class, $result);
        $this->assertInstanceOf(ExtraStub74::class, $result->other);
        $this->assertEquals(11, $result->other->getExtraValue());
        $this->assertCount(2, $result->other->getStubs());
        $this->assertInstanceOf(ExtraStub74::class, $result->other->getStubs()[0]);
        $this->assertInstanceOf(SomeOtherStub74::class, $result->other->getStubs()[1]);
        $this->assertInstanceOf(DateTime::class, $result->other->getStubs()[0]->getDate());
        $this->assertEquals('1983-12-20', $result->other->getStubs()[0]->getDate()->format('Y-m-d'));
    }
    
    public function testShouldUseYmlMetadata()
    {
        $client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        
        $registry = new ClientRegistry();
        $registry->set('some_client', $client);
        
        $metadataFactory = new MetadataFactory(new YmlDriver(__DIR__ . '/../fixtures/metadata', TransferMetadata::class));
        $serializer = new Serializer(
            [
                new Normalizer($metadataFactory), 
                new Denormalizer(new ObjectHydrator($metadataFactory))
            ], 
            [
                new JsonEncoder()
            ]
        );
        
        $webserviceClient = new WebserviceClient($registry, $metadataFactory, $serializer, $serializer);
        
        $this->mockHandler->append(
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'id' => 1,
                    'name' => 'lorem',
                    'e_mail' => 'ipsum',
                ])
            )
        );
        
        $result = $webserviceClient->get(YmlStub74::class, 1);
        
        $this->assertEquals(1, $result->getId());
        $this->assertEquals('lorem', $result->getNome());
        $this->assertEquals('ipsum', $result->getEmail());
    }

    public function testOperations()
    {
        $client = $this->createMock(ClientInterface::class);

        $clientRegistry = $this->createMock(ClientRegistryInterface::class);
        $clientRegistry->expects($this->exactly(5))
            ->method('get')
            ->willReturn($client)
        ;

        $client->expects($this->exactly(5))
            ->method('request')
            ->withConsecutive(
                ['POST', '/test', ['json' => ['id' => null, 'name' => 'a', 'e_mail' => 'b']]],
                ['PUT', '/test/1', ['json' => ['id' => 1, 'name' => 'a', 'e_mail' => 'b']]],
                ['GET', '/test/1', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/test', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/test', ['headers' => ['Content-Type' => 'application/json'], 'query' => ['nome' => 'a']]]
            )
            ->willReturn(
                new Response(
                    201,
                    ['Content-Type' => 'application/json'],
                    json_encode(['id' => 1, 'name' => 'a', 'e_mail' => 'b'])
                ),
                new Response(
                    201,
                    ['Content-Type' => 'application/json'],
                    json_encode(['id' => 1, 'name' => 'a', 'e_mail' => 'b'])
                ),
                new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode(['id' => 1, 'name' => 'a', 'e_mail' => 'b'])
                ),
                new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode([['id' => 1, 'name' => 'a', 'e_mail' => 'b']])
                ),
                new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode([['id' => 1, 'name' => 'a', 'e_mail' => 'b']])
                )
            )
        ;

        $metadataFactory = new MetadataFactory(new YmlDriver(__DIR__ . '/../fixtures/metadata', TransferMetadata::class));
        $serializer = new Serializer(
            [
                new Normalizer($metadataFactory),
                new Denormalizer(new ObjectHydrator($metadataFactory))
            ],
            [
                new JsonEncoder()
            ]
        );

        $webserviceClient = new WebserviceClient($clientRegistry, $metadataFactory, $serializer, $serializer);

        $transferPost = new YmlStub74(null, 'a', 'b');
        $transferPut = new YmlStub74(1, 'a', 'b');

        $webserviceClient->post($transferPost);
        $webserviceClient->put($transferPut);
        $webserviceClient->get(YmlStub74::class, 1);
        $webserviceClient->cGet(YmlStub74::class);
        $webserviceClient->cGet(YmlStub74::class, ['nome' => 'a']);
    }

    public function testShouldThrowException()
    {
        $this->mockHandler->append(
            new Response(
                400,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'violations' => [
                        'otherValue' => 'must be string',
                        'extra_value' => 'higher than 11',
                    ]
                ])
            )
        );

        $webserviceClient = $this->webserviceClient;

        try {
            $webserviceClient->post(new SomeStub74());
        } catch (WebserviceResponseException $ex) {
            $this->assertEquals('must be string', $ex->getBody()['violations']['otherValue']);
            $this->assertEquals(400, $ex->getCode());
            $this->assertEquals('POST', $ex->getRequest()->getMethod());
        }
    }

    /**
     * @expectedException Vox\Webservice\Exception\WebserviceResponseException
     * @expectedExceptionCode 404
     */
    public function testShouldReturnEmptyCollection()
    {
        $this->mockHandler->append(
            new Response(404)
        );

        $this->webserviceClient->cGet(SomeStub74::class);
    }
}

/**
 * @Resource(client="some_client", route="/test")
 */
class SomeStub74
{
    /**
     * @Bindings(source="ds_some")
     *
     */
    public string $some;
    
    /**
     * @Bindings(source="co_other")
     *
     */
    public SomeOtherStub74 $other;
}

/**
 * @Discriminator(map={"some": "Vox\Webservice\SomeOtherStub74", "extra": "Vox\Webservice\ExtraStub74"})
 */
class SomeOtherStub74
{
    /**
     * @var int
     */
    private $otherValue;
    
    public function getOtherValue(): int
    {
        return $this->otherValue;
    }
}

class ExtraStub74 extends SomeOtherStub74
{
    /**
     * @Bindings(source="extra_value")
     *
     * @var int
     */
    private $extraValue;
    
    /**
     * @var array<Vox\Webservice\SomeOtherStub74>
     */
    private $stubs;
    
    /**
     * @var DateTime<Y-m-d>
     */
    private $date;
    
    public function getExtraValue(): int
    {
        return $this->extraValue;
    }
    
    public function getStubs()
    {
        return $this->stubs;
    }
    
    public function getDate(): DateTime
    {
        return $this->date;
    }
}

class YmlStub74
{
    /**
     * @var int
     */
    private $id;
    
    /**
     * @var string
     */
    private $nome;
    
    /**
     * @var string
     */
    private $email;

    public function __construct(int $id = null, string $nome = null, string $email = null)
    {
        $this->id = $id;
        $this->nome = $nome;
        $this->email = $email;
    }

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
}
