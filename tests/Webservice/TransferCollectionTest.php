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
            ->setTransferManager($tm = new TransferManager($mf, $this->createMock(WebserviceClientInterface::class), $pf));

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
        $this->assertTrue(isset($transferCollection[1]));
        $this->assertFalse($transferCollection->forAll(function ($key, $item) {return $item->getId() == 1;}));
        $this->assertCount(5, $transferCollection->getKeys());
        $this->assertCount(5, $transferCollection);
        $this->assertEquals(0, $transferCollection->key());
        $this->assertInstanceOf(AccessInterceptorValueHolderInterface::class, $transferCollection->current());
        $this->assertInstanceOf(AccessInterceptorValueHolderInterface::class, $transferCollection[0]);
        
        $newItem = new CollectionItem();
        $tm->persist($newItem);
        $transferCollection->add($newItem);

        $this->assertCount(6, $transferCollection);
        $this->assertTrue($transferCollection->contains($newItem));
        $this->assertFalse($transferCollection->isEmpty());
        
        $transferCollection->removeElement($newItem);
        $this->assertCount(5, $transferCollection);
        $transferCollection[7] = $newItem;
        $this->assertCount(6, $transferCollection);
        unset($transferCollection[7]);
        $this->assertCount(5, $transferCollection);
        
        $transferCollection->map(function ($current) {
            $this->assertInstanceOf(CollectionItem::class, $current);
        });
        
        $this->assertCount(2, $transferCollection->slice(1, 2));
        
        $partitions = $transferCollection->partition(function ($key, $current) {
            return $current->getName() == "some name 3";
        });
        
        $this->assertCount(2, $partitions[0]);
        $this->assertCount(3, $partitions[1]);
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
