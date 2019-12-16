<?php

namespace Vox\Webservice;

use Doctrine\Common\Annotations\AnnotationReader;
use GuzzleHttp\Client;
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

class TransferRepositoryTest extends TestCase
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

    public function testShouldReturnEmptyCollection()
    {
        $this->guzzleClient->expects($this->once())
            ->method('request')
            ->willReturn(new Response(404))
        ;

        $collection = $this->transferManager->getRepository(RepositoryStub::class)->findBy(['name' => 'foo']);

        $this->assertCount(0, $collection);
    }

    public function testShouldReturnNull()
    {
        $this->guzzleClient->expects($this->once())
            ->method('request')
            ->willReturn(new Response(404))
        ;

        $transfer = $this->transferManager->getRepository(RepositoryStub::class)->findOneBy(['name' => 'foo']);

        $this->assertNull($transfer);
    }

    public function testShouldReturnEmptyCollectionByCriteria()
    {
        $this->guzzleClient->expects($this->once())
            ->method('send')
            ->willReturn(new Response(404))
        ;

        $collection = $this->transferManager->getRepository(RepositoryStub::class)
            ->findByCriteria(new Criteria());

        $this->assertCount(0, $collection);
    }

    public function testShouldReturnNullByCriteria()
    {
        $this->guzzleClient->expects($this->once())
            ->method('send')
            ->willReturn(new Response(404))
        ;

        $transfer = $this->transferManager->getRepository(RepositoryStub::class)
            ->findOneByCriteria(new Criteria());

        $this->assertNull($transfer);
    }
}

/**
 * @Resource(client="foo", route="/foo")
 */
class RepositoryStub
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