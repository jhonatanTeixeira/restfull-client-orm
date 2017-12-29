<?php

namespace Vox\Serializer\Factory;

use Metadata\MetadataFactoryInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface SerializerFactoryInterface
{
    public function createSerialzer(
        MetadataFactoryInterface $metadataFactory,
        string $dateFormat = 'Y-m-d H:i:s'
    ): SerializerInterface;
}
