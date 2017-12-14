<?php

namespace Vox\Webservice\Mapping;

/**
 * maps the conrespondent rest client for a single transfer
 * 
 * @Annotation
 * @Target({"CLASS"})
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
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
