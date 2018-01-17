<?php

namespace Vox\Webservice\Metadata;

use Vox\Metadata\PropertyMetadata;

class IdMetadata
{
    /**
     * @var PropertyMetadata[]
     */
    private $ids = [];

    public function append(PropertyMetadata $metadata)
    {
        $this->ids[] = $metadata;
    }

    public function getValue($object)
    {
        if (!$this->hasIds()) {
            throw new \RuntimeException("transfer " . get_class($object) . " has no id mapping");
        }

        if ($this->isMultiId()) {
            $values = [];

            foreach ($this->ids as $idMetadata) {
                $value = $idMetadata->getValue($object);

                if (!$value) {
                    continue;
                }

                $values[] = sprintf('%s=%s', $idMetadata->name, $idMetadata->getValue($object));
            }

            return implode(';', $values);
        }

        return $this->ids[0]->getValue($object);
    }

    public function hasIds(): bool
    {
        return !empty($this->ids);
    }

    public function getName()
    {
        if (!$this->hasIds()) {
            return;
        }

        if ($this->isMultiId()) {
            return implode(array_map(function ($metadata) {
                return $metadata->name;
            }));
        }

        return $this->ids[0]->name;
    }

    public function isMultiId(): bool
    {
        return count($this->ids) > 1;
    }

    public function getType(): string
    {
        if (!$this->hasIds()) {
            return;
        }

        if ($this->isMultiId()) {
            return 'multi';
        }

        return $this->ids[0]->type;
    }
}