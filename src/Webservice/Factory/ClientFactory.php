<?php

namespace Vox\Webservice\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Vox\Webservice\ClientRegistryInterface;

class ClientFactory
{
    public function addClient(
        string $name,
        string $baseUri,
        ClientRegistryInterface $clientRegistry,
        array $defaults = [],
        array $middlewares = []
    ) {
        $handlerStack = new HandlerStack(new CurlHandler());

        foreach ($middlewares as $middleware) {
            $handlerStack->push($middleware);
        }

        $clientRegistry->set(
            $name,
            new Client(['base_uri' => $baseUri, 'defaults' => $defaults, 'handler' => $handlerStack])
        );
    }
}