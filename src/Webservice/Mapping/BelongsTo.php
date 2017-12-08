<?php

namespace Vox\Webservice\Mapping;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class BelongsTo
{
    /**
     * @var string
     */
    public $foreignField;
}
