<?php

namespace Vox\Webservice;

use Metadata\MetadataFactoryInterface;
use RuntimeException;
use Vox\Webservice\Metadata\TransferMetadata;

trait MetadataTrait
{
     /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;
    
    private function getIdValue($object)
    {
        $id = $this->getClassMetadata($object)->id;
        
        if (!$id) {
            throw new RuntimeException("transfer " . get_class($object) . " has no id mapping");
        }
        
        return $id->getValue($object);
    }
    
    private function getClassMetadata($object): TransferMetadata
    {
        if (is_object($object)) {
            $object = get_class($object);
        }
        
        return $this->metadataFactory->getMetadataForClass($object);
    }
}
