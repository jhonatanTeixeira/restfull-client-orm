<?php

namespace Vox\Webservice;

use Metadata\MetadataFactoryInterface;
use Vox\Webservice\Metadata\TransferMetadata;

/**
 * some metadata utilities
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
trait MetadataTrait
{
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;
    
    private function getIdValue($object)
    {
        return $this->getClassMetadata($object)->id->getValue($object);
    }
    
    private function getClassMetadata($object): TransferMetadata
    {
        if ($object instanceof AccessInterceptorValueHolderInterface) {
            $object = $object->getWrappedValueHolderValue();
        }

        if (is_object($object)) {
            $object = get_class($object);
        }

        return $this->metadataFactory->getMetadataForClass($object);
    }
}
