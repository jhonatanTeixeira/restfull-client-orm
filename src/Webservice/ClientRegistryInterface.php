<?php

namespace Vox\Webservice;

use GuzzleHttp\ClientInterface;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface ClientRegistryInterface
{
    public function set(string $name, ClientInterface $client);
    
    public function get(string $name): ClientInterface;
}
