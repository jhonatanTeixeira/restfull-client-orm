<?php

namespace Vox\Webservice\Proxy;

use ProxyManager\Configuration;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\HasMany;
use Vox\Webservice\Mapping\HasOne;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\TransferManagerInterface;

/**
 * a proxy factory for the transfers, it uses the ocramius proxy generator
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class ProxyFactory implements ProxyFactoryInterface
{
    /**
     * @var AccessInterceptorValueHolderFactory
     */
    private $accessInterceptorFactory;
    
    /**
     * @var Configuration
     */
    private $proxyConfig;

    public function __construct(Configuration $proxyConfig = null)
    {
        $this->accessInterceptorFactory = new AccessInterceptorValueHolderFactory($proxyConfig);
        $this->proxyConfig              = $proxyConfig;
    }
    
    public function createProxy($class, TransferManagerInterface $transferManager): AccessInterceptorValueHolderInterface
    {
        $className = is_object($class) ? get_class($class) : $class;
        $metadata  = $transferManager->getClassMetadata($className);
        $object    = is_object($class) ? $class : new $class();

        $interceptors = [];

        foreach ($metadata->propertyMetadata as $name => $config) {
            $getter = sprintf('get%s', ucfirst($name));

            if (isset($metadata->methodMetadata[$getter])) {
                $interceptors[$getter] = $this->createGetterInterceptor($metadata, $name, $object, $transferManager);
            }
        }

        return $this->accessInterceptorFactory->createProxy($object, $interceptors);
    }

    private function createGetterInterceptor(
        TransferMetadata $metadata,
        string $name,
        $object,
        TransferManagerInterface $transferManager
    ): callable {
        return function () use ($metadata, $name, $object, $transferManager) {
            /* @var $propertyMetadata PropertyMetadata */
            $propertyMetadata = $metadata->propertyMetadata[$name];
            $type             = $propertyMetadata->getParsedType();

            if (class_exists($type)) {
                $this->fetchBelongsTo($metadata, $propertyMetadata, $transferManager, $object, $type);
                $this->fetchHasOne($metadata, $propertyMetadata, $transferManager, $object, $type);
                $this->fetchHasMany($metadata, $propertyMetadata, $transferManager, $object, $type);
            }
        };
    }
    
    private function fetchBelongsTo(
        TransferMetadata $metadata,
        PropertyMetadata $propertyMetadata,
        TransferManagerInterface $transferManager,
        $object,
        string $type
    ) {
        $belongsTo = $propertyMetadata->getAnnotation(BelongsTo::class);
        
        if ($belongsTo instanceof BelongsTo && empty($propertyMetadata->getValue($object))) {
            $data = $transferManager
                ->find($type, $metadata->propertyMetadata[$belongsTo->foreignField]->getValue($object));

            $propertyMetadata->setValue($object, $data);
        }
    }

    private function fetchHasOne(
        TransferMetadata $metadata,
        PropertyMetadata $propertyMetadata,
        TransferManagerInterface $transferManager,
        $object,
        string $type
    ) {
        $hasOne = $propertyMetadata->getAnnotation(HasOne::class);

        if ($hasOne instanceof HasOne) {
            $data = $transferManager->getRepository($type)
                ->findOneBy([$hasOne->foreignField => $metadata->id->getValue($object)]);

            $propertyMetadata->setValue($object, $data);
        }
    }

    private function fetchHasMany(
        TransferMetadata $metadata,
        PropertyMetadata $propertyMetadata,
        TransferManagerInterface $transferManager,
        $object,
        string $type
    ) {
        $hasMany = $propertyMetadata->getAnnotation(HasMany::class);

        if ($hasMany instanceof HasMany) {
            $data = $transferManager->getRepository($type)
                ->findBy([$hasMany->foreignField => $metadata->id->getValue($object)]);

            $propertyMetadata->setValue($object, $data);
        }
    }

    public function registerProxyAutoloader()
    {
        if ($this->proxyConfig) {
            spl_autoload_register($this->proxyConfig->getProxyAutoloader());
        }
    }
}
