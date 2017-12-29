<?php

namespace Vox\Metadata\Factory;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Metadata\Driver\DriverInterface;
use Metadata\MetadataFactory;
use Vox\Metadata\ClassMetadata;
use Vox\Metadata\Driver\AnnotationDriver;
use Vox\Metadata\Driver\YmlDriver;

class MetadataFactoryFactory implements MetadataFactoryFactoryInterface
{
    public function createAnnotationMetadataFactory(
        string $metadataClassName = ClassMetadata::class,
        Reader $reader = null
    ) {
        return new MetadataFactory($this->createAnnotationMetadataDriver($metadataClassName, $reader));
    }
    
    public function createYmlMetadataFactory(string $metadataPath, string $metadataClassName)
    {
        return new MetadataFactory($this->createYmlMetadataDriver($metadataPath, $metadataClassName));
    }
    
    private function createAnnotationMetadataDriver(
        string $metadataClassName = ClassMetadata::class,
        Reader $reader = null
    ): DriverInterface {
        $driver = new AnnotationDriver(
            $reader ?? new AnnotationReader(),
            $metadataClassName
        );

        return $driver;
    }
    
    private function createYmlMetadataDriver(string $metadataPath, string $metadataClassName): DriverInterface
    {
        return new YmlDriver($metadataPath, $metadataClassName);
    }
}
