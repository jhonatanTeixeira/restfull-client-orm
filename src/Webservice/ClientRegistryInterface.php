<?php

namespace Vox\Webservice;

use GuzzleHttp\ClientInterface;

interface ClientRegistryInterface
{
    public function set(string $name, ClientInterface $client);
    
    public function get(string $name): ClientInterface;
}
