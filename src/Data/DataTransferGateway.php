<?php

namespace Vox\Data;

use Metadata\MetadataFactoryInterface;
use Vox\Metadata\ClassMetadata;

class DataTransferGateway
{
    /**
     * @var ObjectGraphBuilderInterface
     */
    private $objectGraphBuilder;
    
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;
    
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;
    
    public function transferData($fromObject, $toObject)
    {
        $this->objectGraphBuilder->clear();
        
        $toObject = $this->objectGraphBuilder->buildObjectGraph($toObject);
        $metadata = $this->getObjectMetadata($fromObject);
        
        foreach ($metadata->propertyMetadata as $propertyMetadata) {
            $path = $propertyMetadata->getAnnotation(Mapping\Bindings::class)->target ?? $propertyMetadata->name;
            $this->propertyAccessor->set($toObject, $path, $propertyMetadata->getValue($fromObject));
        }
        
        return $toObject;
    }
    
    private function getObjectMetadata($class): ClassMetadata
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        
        return $this->metadataFactory->getMetadataForClass($class);
    }
}