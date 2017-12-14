<?php

namespace Vox\Serializer;

use ReflectionClass;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Vox\Data\ObjectHydratorInterface;

/**
 * a data denormalizer aimed to be used with the symfony serializer component
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class Denormalizer implements DenormalizerInterface
{
    /**
     * @var ObjectHydratorInterface
     */
    private $hydrator;
    
    public function __construct(ObjectHydratorInterface $hydrator)
    {
        $this->hydrator = $hydrator;
    }
    
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        if (is_string($class)) {
            $class = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        }
        
        $this->hydrator->hydrate($class, $data);
        
        return $class;
    }
    
    public function supportsDenormalization($data, $type, $format = null)
    {
        return (is_object($type) || class_exists($type)) && is_array($data);
    }
}
