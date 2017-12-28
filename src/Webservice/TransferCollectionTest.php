<?php

namespace Vox\Webservice;

use Doctrine\Common\Annotations\AnnotationReader;
use GuzzleHttp\Psr7\Response;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use Vox\Data\ObjectHydrator;
use Vox\Metadata\Driver\AnnotationDriver;
use Vox\Serializer\Denormalizer;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\Proxy\ProxyFactory;

class TransferCollectionTest extends TestCase
{
    public function testCollection()
    {
        $response = new Response(200, [], json_encode([
            [
                'id' => 1,
                'name' => 'some name 1',
            ],
            [
                'id' => 2,
                'name' => 'some name 2',
            ],
            [
                'id' => 3,
                'name' => 'some name 3',
            ],
            [
                'id' => 4,
                'name' => 'some name 3',
            ],
            [
                'id' => 5,
                'name' => 'some name 4',
            ],
        ]));

        $denormalizer = new Denormalizer(
            new ObjectHydrator($mf = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), TransferMetadata::class)))
        );

        $transferCollection = new TransferCollection(CollectionItem::class, $denormalizer, $response);
        $transferCollection->setProxyFactory($pf = new ProxyFactory())
            ->setTransferManager(new TransferManager($mf, $this->createMock(WebserviceClientInterface::class), $pf));

        $this->assertEquals(1, $transferCollection->first()->getId());
        $this->assertEquals('some name 1', $transferCollection->first()->getName());

        foreach ($transferCollection as $item) {
            $this->assertInstanceOf(CollectionItem::class, $item);
        }

        $this->assertEquals(5, $transferCollection->last()->getId());
        $this->assertEquals('some name 4', $transferCollection->last()->getName());

        foreach ($transferCollection->filter(function ($item) {
            return $item->getName() == 'some name 3';
        }) as $item) {
            $this->assertEquals('some name 3', $item->getName());
        }

        $this->assertTrue($transferCollection->exists(function ($key, $item) {return $item->getId() == 3;}));
        $this->assertCount(5, $transferCollection->getKeys());
        $this->assertEquals(0, $transferCollection->key());
        $this->assertInstanceOf(AccessInterceptorValueHolderInterface::class, $transferCollection->current());
    }
}

class CollectionItem
{
    /**
     * @Id()
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}