<?php

namespace Vox\Webservice\Mapping;

/**
 * annotation used for mapping relational dependency of a transfer
 *
 * @Annotation
 * @Target({"PROPERTY"})
 *
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class HasMany
{
    /**
     * @var string
     */
    public $foreignField;
}