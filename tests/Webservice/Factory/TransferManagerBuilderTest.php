<?php

namespace Vox\Webservice\Factory;

use Doctrine\Common\Cache\Cache;
use PHPUnit\Framework\TestCase;
use Vox\Metadata\Factory\MetadataFactoryFactory;
use Vox\Serializer\Factory\SerializerFactory;
use Vox\Webservice\ClientRegistry;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\WebserviceClientInterface;

class TransferManagerBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        exec('rm -rf /tmp/*.php');
    }

    public function testShouldBuildTransferManagerAnnotationFileCache()
    {
        $builder = new TransferManagerBuilder();

        $builder->withCacheDir('/tmp')
            ->withClientRegistry(new ClientRegistry())
            ->withDebug(true)
            ->withMetadataCache('file')
            ->withMetadataClassName(TransferMetadata::class)
            ->withMetadataDriver('annotation')
        ;

        $transerManager = $builder->createTransferManager();

        $metadata = $transerManager->getClassMetadata(Testing::class);

        $this->assertEquals('string', $metadata->id->getType());
    }

    public function testShouldBuildTransferManagerYmlCached()
    {
        $builder = new TransferManagerBuilder($this->createMock(Cache::class));

        $builder->withCacheDir('/tmp')
            ->withClientRegistry(new ClientRegistry())
            ->withDebug(true)
            ->withMetadataCache('doctrine')
            ->withMetadataClassName(TransferMetadata::class)
            ->withMetadataPath('/tmp')
            ->withMetadataDriver('yaml')
        ;

        $transerManager = $builder->createTransferManager();

        $metadata = $transerManager->getClassMetadata(Testing::class);

        $this->assertEquals('string', $metadata->propertyMetadata['name']->type);
    }
    
    public function testShouldCreateWithDefaults()
    {
        $builder = (new TransferManagerBuilder())
            ->withClientRegistry(new ClientRegistry());
        
        $transferManager = $builder->createTransferManager();
        
        $metadata = $transferManager->getClassMetadata(Testing::class);

        $this->assertEquals('string', $metadata->id->getType());
    }
    
    public function testShouldCreateCustomized()
    {
        $metadadataFactoryFactory = new MetadataFactoryFactory();
        $serializerFactory        = new SerializerFactory();
        $metadataFactory          = $metadadataFactoryFactory->createAnnotationMetadataFactory(TransferMetadata::class);
        
        $builder = (new TransferManagerBuilder())
            ->withClientRegistry(new ClientRegistry())
            ->withMetadaFactory($metadataFactory)
            ->withSerializer($serializerFactory->createSerialzer($metadataFactory))
            ->withWebserviceClient($this->createMock(WebserviceClientInterface::class));
        ;
        
        $transferManager = $builder->createTransferManager();
        
        $metadata = $transferManager->getClassMetadata(Testing::class);

        $this->assertEquals('string', $metadata->id->getType());
    }
}

class Testing
{
    /**
     * @Id
     * @var string
     */
    private $name;
}