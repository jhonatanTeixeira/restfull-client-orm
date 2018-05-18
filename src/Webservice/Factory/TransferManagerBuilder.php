<?php

namespace Vox\Webservice\Factory;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\Cache;
use Metadata\Cache\CacheInterface;
use Metadata\Cache\DoctrineCacheAdapter;
use Metadata\Cache\FileCache;
use Metadata\MetadataFactoryInterface;
use ProxyManager\Configuration;
use RuntimeException;
use Symfony\Component\Serializer\SerializerInterface;
use Vox\Metadata\Factory\MetadataFactoryFactory;
use Vox\Metadata\Factory\MetadataFactoryFactoryInterface;
use Vox\Serializer\Factory\SerializerFactory;
use Vox\Serializer\Factory\SerializerFactoryInterface;
use Vox\Webservice\ClientRegistryInterface;
use Vox\Webservice\Event\PersistenceEvents;
use Vox\Webservice\Event\TransactionEventListener;
use Vox\Webservice\EventDispatcher;
use Vox\Webservice\EventDispatcherInterface;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\Proxy\ProxyFactory;
use Vox\Webservice\Proxy\ProxyFactoryInterface;
use Vox\Webservice\Transaction;
use Vox\Webservice\TransferManager;
use Vox\Webservice\TransferManagerInterface;
use Vox\Webservice\WebserviceClientInterface;

/**
 * Class to help the creation of a TransferManager
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
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
    
    /**
     * @var SerializerFactoryInterface
     */
    private $serializerFactory;
    
    /**
     * @var MetadataFactoryFactoryInterface
     */
    private $metadaFactoryFactory;
    
    /**
     * @var ClientFactory
     */
    private $clientFactory;
    
    /**
     * @var SerializerInterface
     */
    private $serializer;
    
    /**
     * @var MetadataFactoryInterface
     */
    private $metadaFactory;
    
    /**
     * @var WebserviceClientInterface
     */
    private $webserviceClient;
    
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;
    
    /**
     * @var bool
     */
    private $isTransactional = false;

    public function __construct(
        Cache $doctrineCache = null,
        SerializerFactoryInterface $serializerFactory = null,
        MetadataFactoryFactoryInterface $metadaFactoryFactory = null,
        ClientFactory $clientFactory = null
    ) {
        $this->doctrineCache        = $doctrineCache;
        $this->serializerFactory    = $serializerFactory ?? new SerializerFactory();
        $this->metadaFactoryFactory = $metadaFactoryFactory ?? new MetadataFactoryFactory();
        $this->clientFactory        = $clientFactory ?? new ClientFactory();
    }

    private function createMetadataFactory(): MetadataFactoryInterface
    {
        switch ($this->metadataDriver) {
            case 'annotation':
                return $this->metadaFactoryFactory->createAnnotationMetadataFactory(
                    $this->metadataClassName,
                    $this->annotationReader ?? new AnnotationReader()
                );
            case 'yaml':
                return $this->metadaFactoryFactory
                    ->createYmlMetadataFactory($this->metadataPath, $this->metadataClassName);
            default:
                throw new RuntimeException('invalid driver provided');
        }
    }

    private function createMetadataCache(): CacheInterface
    {
        switch ($this->metadataCache) {
            case 'file':
                return new FileCache($this->cacheDir);
            case 'doctrine':
                return new DoctrineCacheAdapter('metadata', $this->doctrineCache);
            default:
                throw new RuntimeException('invalid metadata cache chosen');
        }
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
        $metadataFactory = $this->metadaFactory ?? $this->createMetadataFactory();
        $serializer      = $this->serializer ?? $this->serializerFactory->createSerialzer($metadataFactory);

        if (null !== $this->metadataCache) {
            $metadataFactory->setCache($this->createMetadataCache());
        }

        if (!isset($this->clientRegistry)) {
            throw new RuntimeException('no client registry added');
        }

        $webServiceClient = $this->webserviceClient ?? $this->clientFactory
            ->createWebserviceClient($this->clientRegistry, $metadataFactory, $serializer, $serializer);
        
        if ($this->isTransactional && !isset($this->eventDispatcher)) {
            $this->eventDispatcher = new EventDispatcher();
        }
        
        $transferManager = new TransferManager(
            $metadataFactory,
            $webServiceClient,
            $this->getProxyFactory(),
            $this->eventDispatcher
        );

        if ($this->isTransactional) {
            $this->eventDispatcher->addListener(
                [
                    PersistenceEvents::POST_PERSIST,
                    PersistenceEvents::PRE_UPDATE,
                    PersistenceEvents::POST_REMOVE,
                    PersistenceEvents::ON_EXCEPTION,
                ], 
                new TransactionEventListener(new Transaction($transferManager, $webServiceClient, $metadataFactory))
            );
        }
        
        return $transferManager;
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
    
    public function withSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
        
        return $this;
    }

    public function withMetadaFactory(MetadataFactoryInterface $metadaFactory)
    {
        $this->metadaFactory = $metadaFactory;
        
        return $this;
    }
    
    public function withWebserviceClient(WebserviceClientInterface $webserviceClient)
    {
        $this->webserviceClient = $webserviceClient;
        
        return $this;
    }
    
    public function withEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
        
        return $this;
    }
    
    public function withClientFactory(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
        
        return $this;
    }
    
    public function withDoctrineCache(Cache $doctrineCache)
    {
        $this->doctrineCache = $doctrineCache;
        
        $this->withMetadataCache('doctrine');
        
        return $this;
    }
        
    public function isTransactional(bool $transactional = true)
    {
        $this->isTransactional = $transactional;
        
        return $this;
    }
}
