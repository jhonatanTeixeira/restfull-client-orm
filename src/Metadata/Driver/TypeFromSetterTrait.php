<?php

namespace Vox\Metadata\Driver;

use Exception;
use Metadata\ClassMetadata;
use Metadata\MethodMetadata;
use Metadata\PropertyMetadata;

trait TypeFromSetterTrait
{
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
