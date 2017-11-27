<?php

namespace Vox\Webservice\Mapping;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class Resource
{
    /**
     * @Required
     *
     * @var string
     */
    public $client;
    
    /**
     * @var string
     */
    public $route = "/";
}
