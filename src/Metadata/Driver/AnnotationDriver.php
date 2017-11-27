<?php

namespace Vox\Metadata\Driver;

use Doctrine\Common\Annotations\IndexedReader;
use Doctrine\Common\Annotations\Reader;
use Metadata\ClassMetadata as BaseClassMetadata;
use Metadata\Driver\DriverInterface;
use ReflectionClass;
use Vox\Metadata\ClassMetadata;
use Vox\Metadata\PropertyMetadata;

class AnnotationDriver implements DriverInterface
{
    /**
     * @var Reader
     */
    private $annotationReader;
    
    private $classMetadataClassName;
    
    private $propertyMetadataClassName;
    
    public function __construct(
        Reader $annotationReader,
        string $classMetadataClassName = ClassMetadata::class,
        string $propertyMetadataClassName = PropertyMetadata::class
    ) {
        $this->annotationReader          = new IndexedReader($annotationReader);
        $this->classMetadataClassName    = $classMetadataClassName;
        $this->propertyMetadataClassName = $propertyMetadataClassName;
    }
    
    public function loadMetadataForClass(ReflectionClass $class): BaseClassMetadata
    {
        $classMetadata    = (new ReflectionClass($this->classMetadataClassName))->newInstance($class->name);
        $classAnnotations = $this->annotationReader->getClassAnnotations($class);

        $classMetadata->setAnnotations($classAnnotations);
        
        foreach ($class->getProperties() as $property) {
            $propertyAnnotations = $this->annotationReader->getPropertyAnnotations($property);
            $propertyMetadata = (new ReflectionClass($this->propertyMetadataClassName))
                ->newInstance($class->name, $property->name);
            $propertyMetadata->setAnnotations($propertyAnnotations);
            $classMetadata->addPropertyMetadata($propertyMetadata);
        }
        
        return $classMetadata;
    }
}
