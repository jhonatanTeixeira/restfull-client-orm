<?php

namespace Vox\Webservice\Factory;

use Doctrine\Common\Cache\Cache;
use PHPUnit\Framework\TestCase;
use Vox\Webservice\ClientRegistry;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Metadata\TransferMetadata;

class TransferManagerBuilderTest extends TestCase
{
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

        $this->assertEquals('string', $metadata->id->type);
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
}

class Testing
{
    /**
     * @Id
     * @var string
     */
    private $name;
}