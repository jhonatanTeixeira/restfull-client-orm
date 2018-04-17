<?php

namespace Vox\Webservice;

use BadMethodCallException;
use Metadata\MetadataFactoryInterface;
use SplObjectStorage;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\HasMany;
use Vox\Webservice\Mapping\HasOne;
use Vox\Webservice\Metadata\TransferMetadata;

/**
 * the unit of work keeps track of the transfers current state, works as a sort of memento pattern
 * its really important part of the persistence proccess
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class UnityOfWork implements UnityOfWorkInterface
{
    use MetadataTrait;
    
    /**
     * @var SplObjectStorage
     */
    private $managed;
    
    /**
     * @var SplObjectStorage
     */
    private $removedObjects;

    public function __construct(MetadataFactoryInterface $metadataFactory)
    {
        $this->metadataFactory = $metadataFactory;
        $this->managed         = new SplObjectStorage();
        $this->removedObjects  = new SplObjectStorage();
    }

    public function getIterator()
    {
        return new \ArrayIterator(iterator_to_array($this->managed));
    }

    public function contains($object): bool
    {
        return $this->managed->contains($object);
    }

    public function attach($object)
    {
        $this->managed->attach($object, clone $object);
    }

    public function detach($object)
    {
        $this->managed->detach($object);

        if ($this->removedObjects->contains($object)) {
            $this->removedObjects->detach($object);
        }
    }

    public function isEquals($object): bool
    {
        $metadata     = $this->getClassMetadata($object);
        $storedObject = $this->managed[$object];
        
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
        
        if (!is_integer($internalId)) {
            preg_match('/[^\/]+$/', $internalId, $matches);
            $internalId = $matches[0] ?? null;
        }

        return $externalId != $internalId;
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

        $diff = array_diff_assoc($objectValues, $relatedValues);
        return count($diff) > 0;
    }

    public function fetchByParams(...$params)
    {
        if (count($params) != 2) {
            throw new BadMethodCallException('this method needs two params, $className: string, $id: scalar');
        }

        $className = (string) $params[0];
        $id        = $params[1];

        foreach ($this->managed as $managed) {
            if ($managed instanceof $className && $id == $this->getIdValue($managed)) {
                return $managed;
            }
        }
    }

    public function remove($object)
    {
        if (!$this->managed->contains($object)) {
            throw new \RuntimeException('only managed object can be scheduled to deletion');
        }

        $this->removedObjects->attach($object);
    }

    public function isNew($object): bool
    {
        $id = $this->getIdValue($this->managed[$object]);

        return empty($id);
    }

    public function isDirty($object): bool
    {
        return !$this->isNew($object) && !$this->isEquals($object);
    }

    public function isRemoved($object): bool
    {
        return $this->removedObjects->contains($object);
    }

    public function isDetached($object): bool
    {
        return !$this->managed->contains($object);
    }
}
