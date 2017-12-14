<?php

namespace Vox\Webservice\Proxy;

use Doctrine\Common\Annotations\AnnotationReader;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Vox\Metadata\Driver\AnnotationDriver;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\TransferManager;
use Vox\Webservice\WebserviceClientInterface;

class ProxyFactoryTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        exec('rm -rf /tmp/cache');
        mkdir('/tmp/cache');
    }
    
    public function testShouldCreateProxy()
    {
        $metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), TransferMetadata::class));
        $transferManager = new TransferManager($metadataFactory, $this->createMock(WebserviceClientInterface::class));
        
        $proxyFactory = new ProxyFactory();
        
        $proxy = $proxyFactory->createProxy(new ProxyStub(), $transferManager);
        
        $this->assertInstanceOf(\ProxyManager\Proxy\AccessInterceptorValueHolderInterface::class, $proxy);
    }
    
    public function testShouldSerializeProxy()
    {
        $metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), TransferMetadata::class));
        $transferManager = new TransferManager($metadataFactory, $this->createMock(WebserviceClientInterface::class));
        
        $proxyFactory = new ProxyFactory();
        
        $proxy = $proxyFactory->createProxy(new ProxyStub(), $transferManager);
        
        $data = serialize($proxy);
        
        $proxy = unserialize($data);
        
        $this->assertInstanceOf(\ProxyManager\Proxy\AccessInterceptorValueHolderInterface::class, $proxy);
    }
    
    public function testShouldGetProxyMetadataAndSerialize()
    {
        $metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), TransferMetadata::class));
        $transferManager = new TransferManager($metadataFactory, $this->createMock(WebserviceClientInterface::class));
        
        $proxyFactory = new ProxyFactory();
        
        $proxy = $proxyFactory->createProxy(new ProxyStub(), $transferManager);
        
        $metadata = $metadataFactory->getMetadataForClass(get_class($proxy));
        
        $this->assertEquals('name', $metadata->propertyMetadata['name']->name);
        
        $serialized = serialize($metadata);
        
        $metadata = unserialize($serialized);
        
        $this->assertEquals('name', $metadata->propertyMetadata['name']->name);
    }
    
    /**
     * @runInSeparateProcess
     */
    public function testShouldGetProxyMetadataAndSerializeWithCaching()
    {
        $metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), TransferMetadata::class));
        $metadataFactory->setCache(new \Metadata\Cache\FileCache('/tmp/cache'));
        $transferManager = new TransferManager($metadataFactory, $this->createMock(WebserviceClientInterface::class));
        
        $config = new \ProxyManager\Configuration();
        
        $proxyFactory = new ProxyFactory($config);
        
        $proxy = $proxyFactory->createProxy(new ProxyStub(), $transferManager);
        
        $metadata = $metadataFactory->getMetadataForClass(get_class($proxy));
        
        $this->assertEquals('name', $metadata->propertyMetadata['name']->name);
        
    }
    
    /**
     * @depends testShouldGetProxyMetadataAndSerializeWithCaching
     */
    public function testShouldGetProxyMetadataAndSerializeWithCaching2()
    {
        $metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader(), TransferMetadata::class));
        $metadataFactory->setCache(new \Metadata\Cache\FileCache('/tmp/cache'));
        $transferManager = new TransferManager($metadataFactory, $this->createMock(WebserviceClientInterface::class));

        $proxyFactory = new ProxyFactory();
        
        $proxy = $proxyFactory->createProxy(new ProxyStub(), $transferManager);

        $metadata = $metadataFactory->getMetadataForClass(get_class($proxy));
        
        $this->assertEquals('name', $metadata->propertyMetadata['name']->name);
    }
}


class ProxyStub
{
    private $name;
    
    private $data;
}