<?php

namespace Vox\Webservice\Metadata;

use Metadata\PropertyMetadata as BasePropertyMetadata;
use Vox\Metadata\ClassMetadata;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\Id;

class TransferMetadata extends ClassMetadata
{
    /**
     * @var PropertyMetadata
     */
    public $id;
    
    public function addPropertyMetadata(BasePropertyMetadata $metadata)
    {
        if ($metadata instanceof PropertyMetadata) {
            if ($id = $metadata->getAnnotation(Id::class)) {
                $this->id = $metadata;
            }
        }
        
        parent::addPropertyMetadata($metadata);
    }
}
