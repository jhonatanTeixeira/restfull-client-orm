<?php

namespace Vox\Serializer;

use Metadata\MetadataFactoryInterface;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vox\Data\Mapping\Bindings;
use Vox\Data\Mapping\Exclude;
use Vox\Metadata\ClassMetadata;
use Vox\Metadata\PropertyMetadata;

/**
 * A data normalized aimed to be used with symfony serializer component
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class Normalizer implements NormalizerInterface, NormalizerAwareInterface
{
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * @var NormalizerInterface
     */
    private $normalizer;

    /**
     * @var \SplObjectStorage
     */
    private $storage;
    
    public function __construct(MetadataFactoryInterface $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
        $this->storage = new \SplObjectStorage();
    }
    
    public function normalize($object, $format = null, array $context = [])
    {
        if ($this->storage->offsetExists($object)) {
            return $this->storage[$object];
        }

        $data = $this->extractData($object, $format, $context);

        return $data;
    }

    private function extractData($object, string $format = null, array $context = []): array
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

            if ($propertyMetadata->hasAnnotation(Exclude::class)
                && $propertyMetadata->getAnnotation(Exclude::class)->output) {
                continue;
            }

            if (is_array($value)
                && (preg_match('/\[\]$/', $propertyMetadata->type) || $propertyMetadata->type == 'array')) {
                $items = [];

                foreach ($value as $index => $item) {
                    $items[$index] = $this->normalizeIfSupported($item, $format, $context);
                }

                $value = $items;
            }

            if (is_object($value)) {
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

        $this->storage[$object] = $data;

        return $data;
    }
    
    private function normalizeIfSupported($value, string $format = null, array $context = [])
    {
        if (isset($this->normalizer)) {
            return $this->normalizer->normalize($value, $format, $context);
        }

        if ($this->supportsNormalization($value)) {
            return $this->normalize($value, $format, $context);
        }
        
        return $value;
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return is_object($data) && !$data instanceof \DateTime;
    }

    public function setNormalizer(NormalizerInterface $normalizer)
    {
        $this->normalizer = $normalizer;
    }
}
