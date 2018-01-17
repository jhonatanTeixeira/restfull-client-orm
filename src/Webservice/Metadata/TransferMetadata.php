<?php

namespace Vox\Webservice\Metadata;

use Metadata\PropertyMetadata as BasePropertyMetadata;
use ReflectionClass;
use Vox\Data\Mapping\Exclude;
use Vox\Metadata\ClassMetadata;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\HasMany;
use Vox\Webservice\Mapping\HasOne;
use Vox\Webservice\Mapping\Id;

/**
 * Holds a single transfer metadata information
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class TransferMetadata extends ClassMetadata
{
    /**
     * @var PropertyMetadata[]
     */
    public $id = [];
    
    /**
     * @var PropertyMetadata[]
     */
    public $associations = [];

    public function __construct($name)
    {
        parent::__construct($name);

        $this->id = new IdMetadata();
    }

    public function addPropertyMetadata(BasePropertyMetadata $metadata)
    {
        if ($metadata instanceof PropertyMetadata) {
            if ($id = $metadata->getAnnotation(Id::class)) {
                $this->id->append($metadata);
            }
        }
        
        parent::addPropertyMetadata($metadata);
        
        if ($metadata->hasAnnotation(BelongsTo::class) && !$metadata->hasAnnotation(Exclude::class)) {
            $metadata->annotations[Exclude::class] = new Exclude();
            $metadata->annotations[Exclude::class]->output = false;
        }
        
        if ($metadata->hasAnnotation(BelongsTo::class)
            || $metadata->hasAnnotation(HasOne::class)
            || $metadata->hasAnnotation(HasMany::class)) {
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

        $this->reflection = new ReflectionClass($this->name);
    }
}
