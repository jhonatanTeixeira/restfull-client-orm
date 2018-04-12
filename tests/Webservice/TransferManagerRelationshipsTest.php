<?php

namespace Vox\Webservice;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Vox\Data\Mapping\Exclude;
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

class TransferManagerRelationshipsTest extends TestCase
{
    public function testShouldGetRelationshipsFromAnnotations()
    {
        $metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), TransferMetadata::class));
        $proxyFactory = new ProxyFactory();
        
        $repository = $this->createMock(TransferRepositoryInterface::class);
        
        $transferManager = $this->getMockBuilder(TransferManager::class)
            ->setConstructorArgs([$metadataFactory, $this->createMock(WebserviceClient::class)])
            ->setMethods(['getRepository'])
            ->getMock()
        ;
        
        $repository->expects($this->exactly(3))
            ->method('find')
            ->withConsecutive(
                [1],
                [1],
                ['multiOne=1;multiTwo=1']
            )
            ->willReturnOnConsecutiveCalls(
                $responseStub = $proxyFactory->createProxy(new RelationshipsStub(), $transferManager),
                $responseStub,
                $multiStub = $proxyFactory->createProxy(new MultiStub(), $transferManager)
            )
        ;
        
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['one' => 1])
            ->willReturn($responseStub)
        ;
        
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['many' => 1])
            ->willReturn([$responseStub, $responseStub, $responseStub])
        ;

        $transferManager->expects($this->exactly(5))
            ->method('getRepository')
            ->withConsecutive(
                [RelationshipsStub::class],
                [RelationshipsStub::class],
                [RelationshipsStub::class],
                [RelationshipsStub::class],
                [MultiStub::class]
            )
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
        
        $repository = $this->createMock(TransferRepositoryInterface::class);
        
        $transferManager = $this->getMockBuilder(TransferManager::class)
            ->setConstructorArgs([$metadataFactory, $this->createMock(WebserviceClient::class)])
            ->setMethods(['getRepository'])
            ->getMock()
        ;

        $repository->expects($this->exactly(3))
            ->method('find')
            ->withConsecutive(
                [1],
                [1],
                ['multiOne=1;multiTwo=1']
            )
            ->willReturnOnConsecutiveCalls(
                $responseStub = $proxyFactory->createProxy(new RelationshipsStubYml(), $transferManager),
                $responseStub,
                $multiStub = $proxyFactory->createProxy(new RelationshipsStubYml(), $transferManager)
            )
        ;

        $repository->expects($this->exactly(1))
            ->method('findOneBy')
            ->withConsecutive(
                [['one' => 1]]
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
        
        $guzzleClient->expects($this->exactly(9))
            ->method('request')
            ->withConsecutive(
                ['GET', '/foo/1', ['headers' => ['Content-Type' => 'application/json']]],//#0
                ['GET', '/foo/3', ['headers' => ['Content-Type' => 'application/json']]],//#1
                // first flush, find a registry, gets its relationship, nothing updated
                ['GET', '/foo/2', ['headers' => ['Content-Type' => 'application/json']]],//#2
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
                ]]],//#3
                // second flush, replace a belongs to relationship with another coming fom the webservice
                [
                    'GET', '/bar/multiOne=3;multiTwo=3', [
                        'headers' => ['Content-Type' => 'application/json']
                    ]
                ],//#4
                //third flush, gets a multiple id relationship and does nothing
                ['PUT', '/bar/multiOne=3;multiTwo=3', ['json' => [
                    'multi_one' => 3,
                    'multi_two' => 3,
                    'name'      => 'bar'
                ]]],//#5
                // fourth flush, changes a non id property of the multiple id relationship, update occurs
                ['PUT', '/bar/multiOne=3;multiTwo=2', ['json' => [
                    'multi_one' => 3,
                    'multi_two' => 2,
                    'name'      => 'bar'
                ]]],//#6
                ['PUT', '/foo/1', ['json' => [
                    'id' => 1,
                    'belongs' => 2,
                    'one' => 1,
                    'many' => 1,
                    'multi_one' => 3,
                    'multi_two' => 2,
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
                        'multi_one' => 3,
                        'multi_two' => 2,
                        'name'      => 'bar',
                    ],
                ]]],//#7
                //fifith flush, update an id on a multiple relationship, updates relationship and main object
                ['POST', '/bar', ['json' => [
                    'multi_one' => 3,
                    'multi_two' => 2,
                    'name'      => 'foo'
                ]]]//#8
            )
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode(['id' => 1, 'belongs' => 3, 'multi_two' => 3, 'multi_one' => 3])),
                new Response(200, [], json_encode(['id' => 3])),
                new Response(200, [], json_encode(['id' => 2, 'belongs' => 4])),
                new Response(200, [], json_encode(['id' => 1, 'belongs' => 2])),
                new Response(200, [], json_encode(['multi_two' => 3, 'multi_one' => 3])),
                new Response(200, [], json_encode(['id' => 1])),
                new Response(200, [], json_encode(['multi_two' => 2, 'multi_one' => 3])),
                new Response(200, [], json_encode(['id' => 1])),
                new Response(200, [], json_encode(['multi_two' => 2, 'multi_one' => 3]))
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
        
        $multi = $stub1->getBelongsMulti();
        $transferManager->flush();

        $multi->setName('bar');

        $transferManager->flush();

        $multi->setMultiTwo(2);

        $transferManager->flush();

        $stub1->setBelongsMulti(new MultiStub(null, null));

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

        $guzzleClient->expects($this->exactly(4))
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
                ]]],
                ['DELETE', '/foo/1'],
                ['DELETE', '/foo/2']
            )->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode(['id' => 1])),
                new Response(200, [], json_encode(['id' => 2])),
                new Response(200),
                new Response(200)
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

        $transferManager->remove($one);
        $transferManager->remove($two);

        $transferManager->flush();
    }

    public function testHasRelationships()
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

        $guzzleClient->expects($this->exactly(3))
            ->method('request')
            ->withConsecutive(
                ['GET', '/foo/1', ['headers' => ['Content-Type' => 'application/json']]],//#0
                ['GET', '/foo', ['headers' => ['Content-Type' => 'application/json'], 'query' => ['many' => 1]]],//#1
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
                ]]]//#2
            )
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode(['id' => 1])),
                new Response(200, [], json_encode([])),
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

        /* @var $one RelationshipsStub */
        $one = $transferManager->getRepository(RelationshipsStub::class)->find(1);

        $one->getHasMany()->add(new RelationshipsStub(null));

        $transferManager->flush();
    }
    
    public function testFindWithIri()
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
                ['GET', '/iri/1', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/iri/2', ['headers' => ['Content-Type' => 'application/json']]]
            )->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode([
                    'id' => 1,
                    'belongs' => '/iri/2',
                ])),
                new Response(200, [], json_encode([
                    'id' => 2
                ]))
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
        
        $transfer = $transferManager->find(IriRelationship::class, 1);
        $transfer->getBelongsTo();
    }
    
    public function testSaveWithIri()
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

        $guzzleClient->expects($this->exactly(5))
            ->method('request')
            ->withConsecutive(
                ['GET', '/iri/1', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/iri/3', ['headers' => ['Content-Type' => 'application/json']]],
                ['GET', '/iri/4', ['headers' => ['Content-Type' => 'application/json']]],
                ['POST', '/iri', ['json' => [
                    'id' => null,
                    'belongs' => '/iri/1',
                    'has_many' => null,
                ]]],
                ['PUT', '/iri/1', ['json' => [
                    'id' => 1,
                    'belongs' => null,
                    'has_many' => [
                        '/iri/3',
                        '/iri/4',
                        '/iri/5',
                    ],
                ]]]
            )->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode([
                    'id' => 1,
                    'has_many' => [
                        '/iri/3',
                        '/iri/4',
                    ]
                ])),
                new Response(200, [], json_encode([
                    'id' => 3,
                    'belongs' => '/iri/1',
                ])),
                new Response(200, [], json_encode([
                    'id' => 4,
                    'belongs' => '/iri/1',
                ])),
                new Response(201, [], json_encode([
                    'id' => 5,
                    'belongs' => '/iri/1'
                ])),
                new Response(200, [], json_encode([
                    'id' => 1,
                ]))
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
        
        $transfer = $transferManager->find(IriRelationship::class, 1);
        
        $transfer->getHasManyTransfers()->add((new IriRelationship())->setBelongsTo($transfer));
        
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
     * @var MultiStub
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

    public function getHasMany()
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

    public function getBelongsMulti(): MultiStub
    {
        return $this->belongsMulti;
    }

    public function setBelongsMulti(MultiStub $belongsMulti)
    {
        $this->belongsMulti = $belongsMulti;
    }
}

/**
 * @Resource(client="foo", route="/bar")
 */
class MultiStub
{
    /**
     * @Id()
     *
     * @var int
     */
    private $multiOne = 1;

    /**
     * @Id()
     *
     * @var int
     */
    private $multiTwo = 1;

    /**
     * @var string
     */
    private $name = 'foo';

    public function __construct($multiOne = 1, $multiTwo = 1)
    {
        $this->multiOne = $multiOne;
        $this->multiTwo = $multiTwo;
    }

    public function getMultiOne(): int
    {
        return $this->multiOne;
    }

    public function setMultiOne(int $multiOne)
    {
        $this->multiOne = $multiOne;
    }

    public function getMultiTwo(): int
    {
        return $this->multiTwo;
    }

    public function setMultiTwo(int $multiTwo)
    {
        $this->multiTwo = $multiTwo;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;
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

/**
 * @Resource(client="foo", route="/iri")
 */
class IriRelationship
{
    /**
     * @Id()
     * 
     * @var int
     */
    private $id;
    
    /**
     * @var string
     */
    private $belongs;
    
    /**
     * @BelongsTo(foreignField = "belongs")
     * @Exclude()
     * 
     * @var IriRelationship
     */
    private $belongsTo;

    /**
     * @var array
     */
    private $hasMany;
    
    /**
     * @HasMany(iriCollectionField="hasMany", foreignField="belongs")
     * @Exclude()
     * 
     * @var IriRelationship[]
     */
    private $hasManyTransfers;
    
    public function getId()
    {
        return $this->id;
    }

    public function getBelongs()
    {
        return $this->belongs;
    }

    public function getBelongsTo(): IriRelationship
    {
        return $this->belongsTo;
    }

    public function setBelongs(string $belongs)
    {
        $this->belongs = $belongs;
        
        return $this;
    }

    public function setBelongsTo(IriRelationship $belongsTo)
    {
        $this->belongsTo = $belongsTo;
        
        return $this;
    }
    
    public function getHasMany()
    {
        return $this->hasMany;
    }

    public function getHasManyTransfers(): Collection
    {
        return $this->hasManyTransfers;
    }

    public function setHasMany(array $hasMany)
    {
        $this->hasMany = $hasMany;
        
        return $this;
    }

    public function setHasManyTransfers(Collection $hasManyTransfers)
    {
        $this->hasManyTransfers = $hasManyTransfers;
        
        return $this;
    }
}