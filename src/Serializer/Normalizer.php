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
            $value   = $propertyMetadata->getValue($object);
            
            if (is_array($value) 
                && (preg_match('/\[\]$/', $propertyMetadata->type) || $propertyMetadata->type == 'array')) {
                $items = [];
                
                foreach ($value as $index => $item) {
                    $items[$index] = $this->normalizeIfSupported($item, $format, $context);
                }
                
                $value = $items;
            } else {
                $value = $this->normalizeIfSupported($value, $format, $context);
            }
            
            $target = $binding ? ($binding->target ?? $binding->source ?? null) : $propertyMetadata->name;
            
            if (preg_match('/\./', $target)) {
                $path = implode("']['", explode('.', sprintf("['%s']", $target)));
                eval("\$data$path = \$value;");
            } else {
                $data[$target] = $value;
            }
        }
        
        return $data;
    }
    
    private function normalizeIfSupported($value, $format, array $context)
    {
        if ($this->supportsNormalization($value) 
            && !in_array(spl_object_hash($value), $context['normalized'] ?? [])) {
            $context['normalized'][] = spl_object_hash($value);
            
            return $this->normalize($value, $format, $context);
        }
        
        return $value;
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return is_object($data);
    }
}
