<?php

namespace Vox\Serializer;

use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * this normalizer has the primary goal to aggregate the name converter functionality to the normalization
 * its not optmized for performance though
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class ObjectNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @var Normalizer
     */
    private $normalizer;
    
    /**
     * @var Denormalizer
     */
    private $denormalizer;
    
    /**
     * @var NameConverterInterface
     */
    private $nameConverter;
    
    /**
     * @param Normalizer $normalizer
     * @param Denormalizer $denormalizer
     * @param ClassMetadataFactoryInterface $classMetadataFactory
     * @param NameConverterInterface $nameConverter
     */
    public function __construct(
        Normalizer $normalizer,
        Denormalizer $denormalizer,
        NameConverterInterface $nameConverter = null
    ) {
        $this->normalizer    = $normalizer;
        $this->denormalizer  = $denormalizer;
        $this->nameConverter = $nameConverter ?? new CamelCaseToSnakeCaseNameConverter();
    }
    
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $data = $this->changeKeys($data, false);
        
        return $this->denormalizer->denormalize($data, $class, $format, $context);
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);
        
        return $this->changeKeys($data, true);
    }
    
    private function changeKeys(array $array, bool $direction): array
    {
        $changed = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->changeKeys($value, $direction);
            }
            
            if ($direction) {
                $newKey = $this->nameConverter->normalize($key);
            } else {
                $newKey = $this->nameConverter->denormalize($key);
            }

            $changed[$newKey] = $value;
        }

        return $changed;
    }

    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return $this->denormalizer->supportsDenormalization($data, $type, $format);
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $this->normalizer->supportsNormalization($data, $format);
    }
}
