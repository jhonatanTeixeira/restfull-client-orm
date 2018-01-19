<?php

namespace Vox\Webservice;

use Doctrine\Common\Annotations\AnnotationReader;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Metadata\MetadataFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Vox\Data\ObjectHydrator;
use Vox\Metadata\Driver\AnnotationDriver;
use Vox\Serializer\Denormalizer;
use Vox\Serializer\Normalizer;
use Vox\Serializer\ObjectNormalizer;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\Proxy\ProxyFactory;

class CriteriaTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $guzzleClient;

    /**
     * @var TransferManager
     */
    private $transferManager;

    protected function setUp()
    {
        $proxyFactory = new ProxyFactory();

        $metadataFactory = new MetadataFactory(
            new AnnotationDriver(
                new AnnotationReader(),
                TransferMetadata::class
            )
        );

        $clientRegistry = new ClientRegistry();

        $this->guzzleClient = $guzzleClient = $this->createMock(Client::class);

        $clientRegistry->set('foo', $guzzleClient);

        $serializer = new Serializer([
            new ObjectNormalizer(
                new Normalizer($metadataFactory),
                new Denormalizer(new ObjectHydrator($metadataFactory))
            ),
            [new JsonEncoder()]
        ]);

        $webserviceClient = new WebserviceClient($clientRegistry, $metadataFactory, $serializer, $serializer);

        $this->transferManager = $transferManager = new TransferManager($metadataFactory, $webserviceClient, $proxyFactory);
    }

    public function testShouldFetchCollection()
    {
        $this->markTestIncomplete('todo');

        $this->guzzleClient->expects($this->once())
            ->method('send')
            ->with(new Request('GET', '/route'))
        ;
    }
}