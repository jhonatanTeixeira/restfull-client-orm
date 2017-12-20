<?php

namespace Vox\Webservice\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\RedirectMiddleware;
use Vox\Webservice\ClientRegistry;

class ClientFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testShouldAddClient()
    {
        $registry = new ClientRegistry();

        $factory = new ClientFactory();

        $factory->addClient('example', 'http://example.com', $registry, [], [new RedirectMiddleware(function () {})]);

        $this->assertInstanceOf(Client::class, $registry->get('example'));
    }
}