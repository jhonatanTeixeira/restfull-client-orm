<?php

namespace Vox\Metadata\Driver;

use Doctrine\Common\Annotations\IndexedReader;
use Doctrine\Common\Annotations\Reader;
use Exception;
use Metadata\ClassMetadata as BaseClassMetadata;
use Metadata\Driver\DriverInterface;
use Metadata\MethodMetadata;
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
        /* @var $classMetadata ClassMetadata */
        $classMetadata    = (new ReflectionClass($this->classMetadataClassName))->newInstance($class->name);
        $classAnnotations = $this->annotationReader->getClassAnnotations($class);

        $classMetadata->setAnnotations($classAnnotations);
        
        foreach ($class->getMethods() as $method) {
            $classMetadata->addMethodMetadata(new \Metadata\MethodMetadata($class->name, $method->name));
        }
        
        foreach ($class->getProperties() as $property) {
            $propertyAnnotations = $this->annotationReader->getPropertyAnnotations($property);
            $propertyMetadata = (new ReflectionClass($this->propertyMetadataClassName))
                ->newInstance($class->name, $property->name);
            $propertyMetadata->setAnnotations($propertyAnnotations);
            
            if (property_exists($propertyMetadata, 'type') && empty($propertyMetadata->type)) {
                $propertyMetadata->type = $this->getTypeFromSetter($propertyMetadata, $classMetadata);
            }
            
            $classMetadata->addPropertyMetadata($propertyMetadata);
        }
        
        return $classMetadata;
    }
    
    private function getTypeFromSetter(PropertyMetadata $propertyMetadata, ClassMetadata $classMetadata)
    {
        $setterName = sprintf('set%s', ucfirst($propertyMetadata->name));
        
        $setter = $classMetadata->methodMetadata[$setterName] ?? null;
        
        if ($setter instanceof MethodMetadata) {
            $params = $setter->reflection->getParameters();
            
            if (count($params) == 0) {
                throw new Exception("setter method {$classMetadata->name}:{$setterName} has no params");
            }
            
            if (count($params) > 1) {
                throw new Exception("setter method {$classMetadata->name}:{$setterName} has more than one param");
            }
            
            return $params[0]->getClass() ? $params[0]->getClass()->name : null;
        }
    }
}
