<?php

namespace Vox\Metadata\Factory;

use Doctrine\Common\Annotations\Reader;
use Vox\Metadata\ClassMetadata;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface MetadataFactoryFactoryInterface
{
    public function createAnnotationMetadataFactory(
        string $metadataClassName = ClassMetadata::class,
        Reader $reader = null
    );
    
    public function createYmlMetadataFactory(string $metadataPath, string $metadataClassName);
}
