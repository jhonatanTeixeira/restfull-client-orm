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
    
    /**
     * @var PropertyMetadata
     */
    public $associations;
    
    public function addPropertyMetadata(BasePropertyMetadata $metadata)
    {
        if ($metadata instanceof PropertyMetadata) {
            if ($id = $metadata->getAnnotation(Id::class)) {
                $this->id = $metadata;
            }
        }
        
        parent::addPropertyMetadata($metadata);
        
        if ($metadata->hasAnnotation(\Vox\Webservice\Mapping\BelongsTo::class)) {
            $this->associations[$metadata->name] = $metadata;
        }
        
        return $this;
    }
    
    /**
     * @param string $name
     * @return PropertyMetadata
     */
    public function getAssociation(string $name)
    {
        return $this->associations[$name] ?? null;
    }
    
    public function serialize()
    {
        return serialize(array(
            $this->name,
            $this->methodMetadata,
            $this->propertyMetadata,
            $this->fileResources,
            $this->createdAt,
            $this->annotations,
            $this->id,
            $this->associations,
        ));
    }

    public function unserialize($str)
    {
        list(
            $this->name,
            $this->methodMetadata,
            $this->propertyMetadata,
            $this->fileResources,
            $this->createdAt,
            $this->annotations,
            $this->id,
            $this->associations
        ) = unserialize($str);

        $this->reflection = new \ReflectionClass($this->name);
    }
}
