<?php

namespace Vox\Metadata;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vox\Metadata\Driver\AnnotationDriver;
use Vox\Webservice\Metadata\TransferMetadata;

class MetadataTest extends TestCase
{
    public function testShouldSerializeClassMetadatas()
    {
        $annotationDriver = new AnnotationDriver(new AnnotationReader(), ClassMetadata::class);
        
        $metadata = $annotationDriver->loadMetadataForClass(new ReflectionClass(MetadataStub::class));
        
        $serialized = serialize($metadata);
        
        $unserialized = unserialize($serialized);
        
        $this->assertInstanceOf(ClassMetadata::class, $unserialized);
        $this->assertInstanceOf(PropertyMetadata::class, $unserialized->propertyMetadata['name']);
        $this->assertEquals('lorem', $unserialized->propertyMetadata['name']->getValue(new MetadataStub()));
    }
    
    public function testShouldSerializeTransferMetadatas()
    {
        $annotationDriver = new AnnotationDriver(new AnnotationReader(), TransferMetadata::class);
        
        $metadata = $annotationDriver->loadMetadataForClass(new ReflectionClass(MetadataStub::class));
        
        $serialized = serialize($metadata);
        
        $unserialized = unserialize($serialized);
        
        $this->assertInstanceOf(TransferMetadata::class, $unserialized);
        $this->assertInstanceOf(PropertyMetadata::class, $unserialized->propertyMetadata['name']);
        $this->assertEquals('lorem', $unserialized->propertyMetadata['name']->getValue(new MetadataStub()));
    }
}

class MetadataStub
{
    private $name = 'lorem';
}