<?php

namespace Vox\Webservice;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectRepository;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Vox\Metadata\Driver\AnnotationDriver;
use Vox\Metadata\Driver\YmlDriver;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\HasMany;
use Vox\Webservice\Mapping\HasOne;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\Proxy\ProxyFactory;

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
        
        $transferManager->expects($this->exactly(4))
            ->method('getRepository')
            ->with(RelationshipsStub::class)
            ->willReturn($repository);
        ;
        
        $stub = $transferManager->find(RelationshipsStub::class, 1);
        
        $stub->getBelongsTo();
        $stub->getHasOne();
        $stub->getHasMany();
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
        
        $transferManager->expects($this->exactly(4))
            ->method('getRepository')
            ->with(RelationshipsStubYml::class)
            ->willReturn($repository);
        ;
        
        $stub = $transferManager->find(RelationshipsStubYml::class, 1);
        
        $stub->getBelongsTo();
        $stub->getHasOne();
        $stub->getHasMany();
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
}


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
}