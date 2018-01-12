<?php

namespace Vox\Webservice;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Vox\Data\ObjectHydrator;
use Vox\Metadata\Driver\AnnotationDriver;
use Vox\Metadata\Driver\YmlDriver;
use Vox\Serializer\Denormalizer;
use Vox\Serializer\Normalizer;
use Vox\Serializer\ObjectNormalizer;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\HasMany;
use Vox\Webservice\Mapping\HasOne;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Mapping\Resource;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\Proxy\ProxyFactory;
use function GuzzleHttp\json_encode;

class TransferManagerRelationshipsTest extends TestCase
{
    public function testShouldGetRelationshipsFromAnnotations()
    {
        $metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), TransferMetadata::class));
        $proxyFactory = new ProxyFactory();
        
        $repository = $this->createMock(ObjectRepository::class);
        
        $transferManager = $this->getMockBuilder(TransferManager::class)
            ->setConstructorArgs([$metadataFactory, $this->createMock(WebserviceClient::class)])
            ->setMethods(['getRepository'])
            ->getMock()
        ;
        
        $repository->expects($this->exactly(2))
            ->method('find')
            ->with(1)
            ->willReturn($responseStub = $proxyFactory->createProxy(new RelationshipsStub(), $transferManager))
        ;
        
        $repository->expects($this->exactly(2))
            ->method('findOneBy')
            ->withConsecutive(
                [['one' => 1]],
                [['multiOne' => 1, 'multiTwo' => 1]]
            )
            ->willReturn($responseStub)
        ;
        
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['many' => 1])
            ->willReturn([$responseStub, $responseStub, $responseStub])
        ;

        $transferManager->expects($this->exactly(5))
            ->method('getRepository')
            ->with(RelationshipsStub::class)
            ->willReturn($repository);
        ;
        
        $stub = $transferManager->find(RelationshipsStub::class, 1);
        
        $stub->getBelongsTo();
        $stub->getHasOne();
        $stub->getHasMany();
        $stub->getBelongsMulti();
    }

    public function testShouldGetRelationshipsFromYml()
    {
        $metadataFactory = new MetadataFactory(
            new YmlDriver(
                __DIR__ . '/../fixtures/metadata',
                TransferMetadata::class
            )
        );
        $proxyFactory = new ProxyFactory();
        
        $repository = $this->createMock(ObjectRepository::class);
        
        $transferManager = $this->getMockBuilder(TransferManager::class)
            ->setConstructorArgs([$metadataFactory, $this->createMock(WebserviceClient::class)])
            ->setMethods(['getRepository'])
            ->getMock()
        ;
        
        $repository->expects($this->exactly(2))
            ->method('find')
            ->with(1)
            ->willReturn($responseStub = $proxyFactory->createProxy(new RelationshipsStubYml(), $transferManager))
        ;

        $repository->expects($this->exactly(2))
            ->method('findOneBy')
            ->withConsecutive(
                [['one' => 1]],
                [['multiOne' => 1, 'multiTwo' => 1]]
            )
            ->willReturn($responseStub)
        ;
        
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['many' => 1])
            ->willReturn([$responseStub, $responseStub, $responseStub])
        ;
        
        $transferManager->expects($this->exactly(5))
            ->method('getRepository')
            ->with(RelationshipsStubYml::class)
            ->willReturn($repository);
        ;
        
        $stub = $transferManager->find(RelationshipsStubYml::class, 1);
        
        $stub->getBelongsTo();
        $stub->getHasOne();
        $stub->getHasMany();
        $stub->getBelongsMulti();
    }

    public function testShouldPersistRelationships()
    {
        $stub = new RelationshipsStub(null);

        $stub->setBelongsTo(new RelationshipsStub(null));
        $stub->setHasOne(new RelationshipsStub(null));
        $stub->setHasMany(new ArrayCollection([
            new RelationshipsStub(null),
            new RelationshipsStub(null),
            new RelationshipsStub(null),
        ]));

        $metadataFactory = new MetadataFactory(
            new AnnotationDriver(
                new AnnotationReader(),
                TransferMetadata::class
            )
        );

        $id = 0;

        $webserviceClient = $this->createMock(WebserviceClient::class);
        $webserviceClient->expects($this->exactly(6))
            ->method('post')
            ->willReturnCallback(function ($entity) use (&$id) {
                $entity->setId(++$id);
            });
        ;

        $transferManager = $this->getMockBuilder(TransferManager::class)
            ->setConstructorArgs([$metadataFactory, $webserviceClient])
            ->setMethods(['getRepository'])
            ->getMock()
        ;

        $transferManager->persist($stub);

        $transferManager->flush();
    }

    public function testOnlyRelationshipChanged()
    {
        $proxyFactory = new ProxyFactory();

        $metadataFactory = new MetadataFactory(
            new AnnotationDriver(
                new AnnotationReader(),
                TransferMetadata::class
            )
        );

        $webserviceClient = $this->createMock(WebserviceClient::class);
        $webserviceClient->expects($this->once())
            ->method('put')
            ->willReturnCallback(function ($entity) {
                return $entity;
            });
        ;

        $webserviceClient->expects($this->exactly(3))
            ->method('get')
            ->willReturnCallback(function ($name, $id) {
                return new $name($id);
            });
        ;

        $transferManager = new TransferManager($metadataFactory, $webserviceClient, $proxyFactory);

        $stub1 = $transferManager->find(RelationshipsStub::class, 1);
        $stub1->getBelongsTo();
        $transferManager->flush();

        $stub2 = $transferManager->find(RelationshipsStub::class, 2);

        $stub1->setBelongsTo($stub2);
        $transferManager->flush();
    }

    public function testRelationshipChangedCallCorrectUrls()
    {
        $proxyFactory = new ProxyFactory();

        $metadataFactory = new MetadataFactory(
            new AnnotationDriver(
                new AnnotationReader(),
                TransferMetadata::class
            )
        );
        
        $clientRegistry = new ClientRegistry();
        
        $guzzleClient = $this->createMock(Client::class);
        
        $guzzleClient->expects($this->exactly(6))
            ->method('request')
            ->withConsecutive(
                ['GET', '/foo/1', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/foo/3', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/foo/2', ['headers' => ['Content-Type' => 'application/json']]],
                ['PUT', '/foo/1', ['json' => [
                    'id' => 1,
                    'belongs' => 2,
                    'one' => 1,
                    'many' => 1,
                    'multi_one' => 3,
                    'multi_two' => 3,
                    'belongs_to' => [
                        'id' => 2,
                        'belongs' => 4,
                        'one' => 1,
                        'many' => 1,
                        'multi_one' => 1,
                        'multi_two' => 1,
                        'belongs_to' => null,
                        'has_one' => null,
                        'has_many' => null,
                        'belongs_multi' => null
                    ],
                    'has_one' => null,
                    'has_many' => null,
                    'belongs_multi' => null,
                ]]],
                [
                    'GET', '/foo', [
                        'headers' => ['Content-Type' => 'application/json'], 
                        'query' => ['multiTwo' => 3, 'multiOne' => 3]
                    ]
                ],
                ['PUT', '/foo/1', ['json' => [
                    'id' => 1,
                    'belongs' => 2,
                    'one' => 1,
                    'many' => 1,
                    'multi_one' => 1,
                    'multi_two' => 1,
                    'belongs_to' => [
                        'id' => 2,
                        'belongs' => 4,
                        'one' => 1,
                        'many' => 1,
                        'multi_one' => 1,
                        'multi_two' => 1,
                        'belongs_to' => null,
                        'has_one' => null,
                        'has_many' => null,
                        'belongs_multi' => null
                    ],
                    'has_one' => null,
                    'has_many' => null,
                    'belongs_multi' => [
                        'id' => 2,
                        'belongs' => 4,
                        'one' => 1,
                        'many' => 1,
                        'multi_one' => 1,
                        'multi_two' => 1,
                        'belongs_to' => null,
                        'has_one' => null,
                        'has_many' => null,
                        'belongs_multi' => null
                    ],
                ]]]
            )
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode(['id' => 1, 'belongs' => 3, 'multi_two' => 3, 'multi_one' => 3])),
                new Response(200, [], json_encode(['id' => 3])),
                new Response(200, [], json_encode(['id' => 2, 'belongs' => 4])),
                new Response(200, [], json_encode(['id' => 1, 'belongs' => 2])),
                new Response(200, [], json_encode([['id' => 5, 'belongs' => 2, 'multi_two' => 3, 'multi_one' => 3]])),
                new Response(200, [], json_encode(['id' => 1]))
            )
        ;
        
        $clientRegistry->set('foo', $guzzleClient);
        
        $serializer = new Serializer([
            new ObjectNormalizer(
                new Normalizer($metadataFactory),
                new Denormalizer(new ObjectHydrator($metadataFactory))
            ),
            [new JsonEncoder()]
        ]);
        
        $webserviceClient = new WebserviceClient($clientRegistry, $metadataFactory, $serializer, $serializer);

        $transferManager = new TransferManager($metadataFactory, $webserviceClient, $proxyFactory);

        $stub1 = $transferManager->find(RelationshipsStub::class, 1);
        $stub1->getBelongsTo();
        $transferManager->flush();

        $stub2 = $transferManager->find(RelationshipsStub::class, 2);

        $stub1->setBelongsTo($stub2);
        $transferManager->flush();
        
        $stub1->getBelongsMulti();
        $transferManager->flush();

        $stub1->setBelongsMulti($stub2);
        $transferManager->flush();
    }

    public function testShouldPostMultiple()
    {
        $proxyFactory = new ProxyFactory();

        $metadataFactory = new MetadataFactory(
            new AnnotationDriver(
                new AnnotationReader(),
                TransferMetadata::class
            )
        );

        $clientRegistry = new ClientRegistry();

        $guzzleClient = $this->createMock(Client::class);

        $guzzleClient->expects($this->exactly(2))
            ->method('request')
            ->withConsecutive(
                ['POST', '/foo', ['json' => [
                    'id' => null,
                    'belongs' => 1,
                    'one' => 1,
                    'many' => 1,
                    'multi_one' => 1,
                    'multi_two' => 1,
                    'belongs_to' => null,
                    'has_one' => null,
                    'has_many' => null,
                    'belongs_multi' => null,
                ]]],
                ['POST', '/foo', ['json' => [
                    'id' => null,
                    'belongs' => 1,
                    'one' => 1,
                    'many' => 1,
                    'multi_one' => 1,
                    'multi_two' => 1,
                    'belongs_to' => null,
                    'has_one' => null,
                    'has_many' => null,
                    'belongs_multi' => null,
                ]]]
            )->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode(['id' => 1])),
                new Response(200, [], json_encode(['id' => 2]))
            )
        ;

        $clientRegistry->set('foo', $guzzleClient);

        $serializer = new Serializer([
            new ObjectNormalizer(
                new Normalizer($metadataFactory),
                new Denormalizer(new ObjectHydrator($metadataFactory))
            ),
            [new JsonEncoder()]
        ]);

        $webserviceClient = new WebserviceClient($clientRegistry, $metadataFactory, $serializer, $serializer);

        $transferManager = new TransferManager($metadataFactory, $webserviceClient, $proxyFactory);

        $one = new RelationshipsStub(null);
        $two = new RelationshipsStub(null);

        $transferManager->persist($one);
        $transferManager->persist($two);

        $transferManager->flush();
    }
}

/**
 * @Resource(client="foo", route="/foo")
 */
class RelationshipsStub
{
    /**
     * @Id
     *
     * @var int
     */
    private $id = 1;
    
    /**
     * @var int
     */
    private $belongs = 1;
    
    /**
     * @var int
     */
    private $one = 1;
    
    /**
     * @var int
     */
    private $many = 1;

    /**
     * @var int
     */
    private $multiOne = 1;

    /**
     * @var int
     */
    private $multiTwo = 1;
    
    /**
     * @BelongsTo(foreignField = "belongs")
     * 
     * @var RelationshipsStub
     */
    private $belongsTo;
    
    /**
     * @HasOne(foreignField = "one")
     * 
     * @var RelationshipsStub
     */
    private $hasOne;
    
    /**
     * @HasMany(foreignField = "many")
     *
     * @var RelationshipsStub[]
     */
    private $hasMany;

    /**
     * @BelongsTo(foreignField={"multiOne", "multiTwo"})
     *
     * @var RelationshipsStub
     */
    private $belongsMulti;

    public function __construct($id = 1)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getBelongs()
    {
        return $this->belongs;
    }

    public function getOne()
    {
        return $this->one;
    }

    public function getMany()
    {
        return $this->many;
    }

    public function getBelongsTo(): RelationshipsStub
    {
        return $this->belongsTo;
    }

    public function getHasOne(): RelationshipsStub
    {
        return $this->hasOne;
    }

    public function getHasMany(): array
    {
        return $this->hasMany;
    }

    public function setBelongsTo(RelationshipsStub $belongsTo)
    {
        $this->belongsTo = $belongsTo;

        return $this;
    }

    public function setHasOne(RelationshipsStub $hasOne)
    {
        $this->hasOne = $hasOne;

        return $this;
    }

    public function setHasMany(Collection $hasMany)
    {
        $this->hasMany = $hasMany;

        return $this;
    }

    public function setBelongs(int $belongs)
    {
        $this->belongs = $belongs;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getBelongsMulti(): RelationshipsStub
    {
        return $this->belongsMulti;
    }

    public function setBelongsMulti(RelationshipsStub $belongsMulti)
    {
        $this->belongsMulti = $belongsMulti;
    }
}

class RelationshipsStubYml
{
    /**
     * @var int
     */
    private $id = 1;
    
    /**
     * @var int
     */
    private $belongs = 1;
    
    /**
     * @var int
     */
    private $one = 1;
    
    /**
     * @var int
     */
    private $many = 1;


    /**
     * @var int
     */
    private $multiOne = 1;

    /**
     * @var int
     */
    private $multiTwo = 1;

    /**
     * @var RelationshipsStubYml
     */
    private $belongsTo;
    
    /**
     * @var RelationshipsStubYml
     */
    private $hasOne;
    
    /**
     * @var RelationshipsStubYml[]
     */
    private $hasMany;

    /**
     * @var RelationshipsStubYml
     */
    private $belongsMulti;

    public function getId()
    {
        return $this->id;
    }

    public function getBelongs()
    {
        return $this->belongs;
    }

    public function getOne()
    {
        return $this->one;
    }

    public function getMany()
    {
        return $this->many;
    }

    public function getBelongsTo(): RelationshipsStubYml
    {
        return $this->belongsTo;
    }

    public function getHasOne(): RelationshipsStubYml
    {
        return $this->hasOne;
    }

    public function getHasMany(): array
    {
        return $this->hasMany;
    }

    public function getBelongsMulti(): RelationshipsStubYml
    {
        return $this->belongsMulti;
    }
}