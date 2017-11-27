<?php

namespace Vox\Serializer;

use Metadata\MetadataFactoryInterface;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vox\Data\Mapping\Bindings;
use Vox\Metadata\ClassMetadata;
use Vox\Metadata\PropertyMetadata;

class Normalizer implements NormalizerInterface
{
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;
    
    public function __construct(MetadataFactoryInterface $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }
    
    public function normalize($object, $format = null, array $context = array())
    {
        $objectMetadata = $this->metadataFactory->getMetadataForClass(get_class($object));
        
        if (!$objectMetadata instanceof ClassMetadata) {
            throw new RuntimeException('invalid metadata class');
        }
        
        $data = [];
        $data['type'] = get_class($object);
        
        /* @var $propertyMetadata PropertyMetadata */
        foreach ($objectMetadata->propertyMetadata as $propertyMetadata) {
            $binding = $propertyMetadata->getAnnotation(Bindings::class);
            
            $value  = $propertyMetadata->getValue($object);
            
            if ($this->supportsNormalization($value) 
                && !in_array(spl_object_hash($value), $context['normalized'] ?? [])) {
                $context['normalized'][] = spl_object_hash($value);
                $value = $this->normalize($value, $format, $context);
            }
            
            $target        = $binding ? ($binding->target ?? $binding->source ?? null) : $propertyMetadata->name;
            $data[$target] = $value;
        }
        
        return $data;
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return is_object($data);
    }
}
