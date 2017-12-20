<?php

namespace Vox\Webservice\Factory;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\Cache;
use Metadata\Cache\CacheInterface;
use Metadata\Cache\DoctrineCacheAdapter;
use Metadata\Cache\FileCache;
use Metadata\Driver\DriverInterface;
use Metadata\MetadataFactory;
use ProxyManager\Configuration;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Serializer;
use Vox\Data\ObjectHydrator;
use Vox\Metadata\Driver\AnnotationDriver;
use Vox\Metadata\Driver\YmlDriver;
use Vox\Serializer\Denormalizer;
use Vox\Serializer\Normalizer;
use Vox\Serializer\ObjectNormalizer;
use Vox\Webservice\ClientRegistryInterface;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\Proxy\ProxyFactory;
use Vox\Webservice\Proxy\ProxyFactoryInterface;
use Vox\Webservice\TransferManager;
use Vox\Webservice\TransferManagerInterface;
use Vox\Webservice\WebserviceClient;

class TransferManagerBuilder
{
    /**
     * @var ProxyFactoryInterface
     */
    private $proxyFactory;

    /**
     * @var string
     */
    private $metadataCache;

    /**
     * @var string
     */
    private $cacheDir = '/tmp/cache';

    /**
     * @var string
     */
    private $metadataDriver = 'annotation';

    /**
     * @var ClientRegistryInterface
     */
    private $clientRegistry;

    /**
     * @var string
     */
    private $metadataPath;

    /**
     * @var string
     */
    private $metadataClassName = TransferMetadata::class;

    /**
     * @var bool 
     */
    private $debug = false;

    /**
     * @var Cache
     */
    private $doctrineCache;

    public function __construct(Cache $doctrineCache = null)
    {
        $this->doctrineCache = $doctrineCache;
    }

    private function createMetadataDriver(): DriverInterface
    {
        switch ($this->metadataDriver) {
            case 'annotation':
                $driver = new AnnotationDriver(
                    $this->annotationReader ?? new AnnotationReader(),
                    $this->metadataClassName
                );
                break;
            case 'yaml':
                $driver = new YmlDriver($this->metadataPath, $this->metadataClassName);
                break;
            default:
                throw new \RuntimeException('invalid driver provided');
        }

        return $driver;
    }

    private function createMetadataCache(): CacheInterface
    {
        switch ($this->metadataCache) {
            case 'file':
                $cache = new FileCache($this->cacheDir);
                break;
            case 'doctrine':
                $cache = new DoctrineCacheAdapter('metadata', $this->doctrineCache);
                break;
            default:
                throw new \RuntimeException('invalid metadata cache chosen');
        }

        return $cache;
    }

    private function getProxyFactory(): ProxyFactoryInterface
    {
        if (isset($this->proxyFactory)) {
            return $this->proxyFactory;
        }

        $config = new Configuration();
        $config->setProxiesTargetDir($this->cacheDir);
        $proxyFactory = new ProxyFactory($config);
        $proxyFactory->registerProxyAutoloader();

        return $this->proxyFactory = $proxyFactory;
    }

    public function createTransferManager(): TransferManagerInterface
    {
        $driver           = $this->createMetadataDriver();
        $metadataFactory  = new MetadataFactory($driver, ClassHierarchyMetadata::class, $this->debug);
        $objectHydrator   = new ObjectHydrator($metadataFactory);
        $normalizer       = new Normalizer($metadataFactory);
        $denormalizer     = new Denormalizer($objectHydrator);
        $objectNormalizer = new ObjectNormalizer($normalizer, $denormalizer);
        $serializer       = new Serializer(
            [$objectNormalizer, new DateTimeNormalizer()],
            [new JsonEncoder(), new XmlEncoder()]
        );

        if (null !== $this->metadataCache) {
            $metadataFactory->setCache($this->createMetadataCache());
        }

        if (!isset($this->clientRegistry)) {
            throw new \RuntimeException('no client registry added');
        }

        $webServiceClient = new WebserviceClient($this->clientRegistry, $metadataFactory, $serializer, $serializer);

        return new TransferManager($metadataFactory, $webServiceClient, $this->getProxyFactory());
    }

    public function withProxyFactory(ProxyFactoryInterface $proxyFactory)
    {
        $this->proxyFactory = $proxyFactory;

        return $this;
    }

    public function withMetadataCache(string $metadataCache)
    {
        $this->metadataCache = $metadataCache;

        return $this;
    }

    public function withCacheDir(string $cacheDir)
    {
        $this->cacheDir = $cacheDir;

        return $this;
    }

    public function withMetadataDriver(string $metadataDriver)
    {
        $this->metadataDriver = $metadataDriver;

        return $this;
    }

    public function withClientRegistry(ClientRegistryInterface $clientRegistry)
    {
        $this->clientRegistry = $clientRegistry;

        return $this;
    }

    public function withMetadataPath(string $metadataPath)
    {
        $this->metadataPath = $metadataPath;

        return $this;
    }

    public function withMetadataClassName(string $metadataClassName)
    {
        $this->metadataClassName = $metadataClassName;

        return $this;
    }

    public function withDebug(bool $debug)
    {
        $this->debug = $debug;

        return $this;
    }
}