<?php

namespace Vox\Webservice;

use GuzzleHttp\ClientInterface;
use RuntimeException;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class ClientRegistry implements ClientRegistryInterface
{
    private $clients = [];
    
    public function get(string $name): ClientInterface
    {
        if (!isset($this->clients[$name])) {
            throw new RuntimeException("no client registered for $name");
        }
        
        return $this->clients[$name];
    }

    public function set(string $name, ClientInterface $client)
    {
        $this->clients[$name] = $client;
        
        return $this;
    }
}
