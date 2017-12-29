<?php

namespace Vox\Serializer\Factory;

use Metadata\MetadataFactoryInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Vox\Data\ObjectHydrator;
use Vox\Serializer\Denormalizer;
use Vox\Serializer\Normalizer;
use Vox\Serializer\ObjectNormalizer;

class SerializerFactory
{
    public function createSerialzer(
        MetadataFactoryInterface $metadataFactory,
        string $dateFormat = 'Y-m-d H:i:s'
    ): SerializerInterface {
        return new Serializer(
            [
                new ObjectNormalizer(
                    new Normalizer($metadataFactory),
                    new Denormalizer(new ObjectHydrator($metadataFactory))
                ),
                new DateTimeNormalizer($dateFormat)
            ], 
            [
                new JsonEncoder(),
                new XmlEncoder()
            ]
        );
    }
}
