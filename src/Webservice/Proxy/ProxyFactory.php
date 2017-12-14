<?php

namespace Vox\Webservice\Proxy;

use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Metadata\TransferMetadata;
use Vox\Webservice\TransferManagerInterface;

class ProxyFactory implements ProxyFactoryInterface
{
    /**
     * @var AccessInterceptorValueHolderFactory
     */
    private $accessInterceptorFactory;
    
    /**
     * @var \ProxyManager\Configuration
     */
    private $proxyConfig;

    public function __construct(\ProxyManager\Configuration $proxyConfig = null)
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
            $type             = $propertyMetadata->type;

            if (class_exists($type)) {
                $belongsTo = $propertyMetadata->getAnnotation(BelongsTo::class);

                if ($belongsTo instanceof BelongsTo && empty($propertyMetadata->getValue($object))) {
                    $data = $transferManager
                        ->find($type, $metadata->propertyMetadata[$belongsTo->foreignField]->getValue($object));

                    $propertyMetadata->setValue($object, $data);
                }
            }
        };
    }

    public function registerProxyAutoloader()
    {
        if ($this->proxyConfig) {
            spl_autoload_register($this->proxyConfig->getProxyAutoloader());
        }
    }
}
