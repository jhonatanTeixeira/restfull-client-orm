<?php

namespace Vox\Webservice;

use Doctrine\Common\Annotations\AnnotationReader;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Mapping\Resource;
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

    protected function setUp(): void
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
        $this->guzzleClient->expects($this->once())
            ->method('send')
            ->with(new Request('GET', '/foo?name=abc', ['Content-Type' => 'application/json']))
            ->willReturn(new Response(200, [], json_encode([
                ['id' => 1, 'name' => 'abc'],
                ['id' => 2, 'name' => 'abc'],
            ])));
        ;

        $criteria = new Criteria();
        $criteria->withQuery(['name' => 'abc']);

        $collection = $this->transferManager->getRepository(CriteriaStub::class)->findByCriteria($criteria);

        $this->assertCount(2, $collection);
    }

    public function testShouldFetchItem()
    {
        $this->guzzleClient->expects($this->once())
            ->method('send')
            ->with(new Request('GET', '/foo/uf/1/city/bar?name=abc', ['Content-Type' => 'application/json']))
            ->willReturn(new Response(200, [], json_encode(
                ['id' => 1, 'name' => 'abc']
            )));
        ;

        $criteria = new Criteria();
        $criteria->withParams(['uf' => 1])
            ->setParam('city', 'bar')
            ->setQuery('name', 'abc');

        $transfer = $this->transferManager->getRepository(CriteriaStub::class)->findOneByCriteria($criteria);

        $this->assertEquals(1, $transfer->getId());
    }
}

/**
 * @Resource(client="foo", route="/foo")
 */
class CriteriaStub
{
    /**
     * @Id()
     *
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }
}