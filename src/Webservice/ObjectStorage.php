<?php

namespace Vox\Webservice;

use InvalidArgumentException;
use Metadata\MetadataFactoryInterface;
use BadMethodCallException;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\HasMany;
use Vox\Webservice\Mapping\HasOne;
use Vox\Webservice\Metadata\TransferMetadata;

/**
 * A object storage using an id hashmap pattern
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class ObjectStorage implements ObjectStorageInterface
{
    use MetadataTrait;
    
    private $storage = [];
    
    public function __construct(MetadataFactoryInterface $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
    }
    
    public function contains($object): bool
    {
        return isset($this->storage[get_class($object)][$this->getIdValue($object)]);
    }
    
    public function attach($object)
    {
        $className = get_class($object);
        $id        = $this->getIdValue($object);
        
        if (!$id) {
            throw new InvalidArgumentException('object has no id value');
        }
        
        $this->storage[$className][$id] = $object;
    }
    
    public function detach($object)
    {
        unset($this->storage[get_class($object)][$this->getIdValue($object)]);
    }

    public function getIterator()
    {
        $items = [];
        
        foreach ($this->storage as $transferData) {
            foreach ($transferData as $item) {
                $items[] = $item;
            }
        }
        
        return new \ArrayIterator($items);
    }

    public function isEquals($object): bool
    {
        $className    = get_class($object);
        $metadata     = $this->getClassMetadata($object);
        $idValue      = $this->getIdValue($object);
        $storedObject = $this->storage[$className][$idValue];

        /* @var $propertyMetadata PropertyMetadata */
        foreach ($metadata->propertyMetadata as $name => $propertyMetadata) {
            $storedValue = $propertyMetadata->getValue($storedObject);
            $value       = $propertyMetadata->getValue($object);

            if ($propertyMetadata->hasAnnotation(BelongsTo::class)) {
                if (!empty($value)
                    && $this->hasRelationshipChanged($object, $value, $propertyMetadata, $metadata)) {
                    return false;
                }

                continue;
            }

            if ($propertyMetadata->hasAnnotation(HasOne::class) || $propertyMetadata->hasAnnotation(HasMany::class)) {
                continue;
            }

            if ($storedValue != $value) {
                return false;
            }
        }

        return true;
    }

    private function hasRelationshipChanged(
        $object,
        $related,
        PropertyMetadata $propertyMetadata,
        TransferMetadata $metadata
    ): bool {
        /* @var $belongsTo BelongsTo */
        $belongsTo  = $propertyMetadata->getAnnotation(BelongsTo::class);

        if (is_array($belongsTo->foreignField)) {
            return $this->hasMultiFieldsRelationshipChanged($object, $related, $belongsTo->foreignField);
        }

        $externalId = $this->getIdValue($related);
        $internalId = $metadata->propertyMetadata[$belongsTo->foreignField]->getValue($object);

        if ($externalId !== $internalId) {
            $metadata->propertyMetadata[$belongsTo->foreignField]->setValue($object, $externalId);
            return true;
        }

        return false;
    }

    private function hasMultiFieldsRelationshipChanged(
        $object,
        $related,
        array $fields
    ): bool {
        $objectValues  = [];
        $relatedValues = [];

        $objectMetadata  = $this->getClassMetadata($object);
        $relatedMetadata = $this->getClassMetadata($related);

        foreach ($fields as $field) {
            $objectValues[$field]  = $objectMetadata->propertyMetadata[$field]->getValue($object);
            $relatedValues[$field] = $relatedMetadata->propertyMetadata[$field]->getValue($related);
        }

        $has = count(array_diff_assoc($objectValues, $relatedValues)) > 0;

        if ($has) {
            foreach ($relatedValues as $field => $value) {
                $objectMetadata->propertyMetadata[$field]->setValue($object, $value);
            }
        }

        return $has;
    }

    /**
     * @param string $className
     * @param scalar $id
     *
     * @return object
     */
    public function fetchByParams(...$params)
    {
        if (count($params) != 2) {
            throw new BadMethodCallException('this method needs two params, $className: string, $id: scalar');
        }

        $className = (string) $params[0];
        $id        = $params[1];

        if (!$id) {
            throw new BadMethodCallException('$id must be valid value');
        }

        return $this->storage[$className][$id] ?? null;
    }
}
