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

            if (is_array($belongsTo->foreignField)) {
                $this->fetchBelongsToMulti($object, $type, $metadata, $propertyMetadata, $belongsTo, $transferManager);
            } else {
                $this->fetchBelongsToSingle($object, $type, $metadata, $propertyMetadata, $belongsTo, $transferManager);
            }
        }
    }

    private function fetchBelongsToSingle(
        $object,
        string $type,
        TransferMetadata $metadata,
        PropertyMetadata $propertyMetadata,
        BelongsTo $belongsTo,
        TransferManagerInterface $transferManager
    ) {
        $idValue  = $metadata->propertyMetadata[$belongsTo->foreignField]->getValue($object);

        if (!$idValue) {
            return;
        }

        $data = $transferManager
            ->find($type, $idValue);

        $propertyMetadata->setValue($object, $data);
    }

    private function fetchBelongsToMulti(
        $object,
        string $type,
        TransferMetadata $metadata,
        PropertyMetadata $propertyMetadata,
        BelongsTo $belongsTo,
        TransferManagerInterface $transferManager

    ) {
        $criteria = [];
        
        foreach ($belongsTo->foreignField as $field) {
            $criteria[] = sprintf('%s=%s', $field, $metadata->propertyMetadata[$field]->getValue($object));
            //$criteria[$field] = $metadata->propertyMetadata[$field]->getValue($object);
        }

        $data = $transferManager->getRepository($type)
            ->find(implode(';', $criteria));

        $propertyMetadata->setValue($object, $data);
    }

    private function fetchHasOne(
        TransferMetadata $metadata,
        PropertyMetadata $propertyMetadata,
        TransferManagerInterface $transferManager,
        $object,
        string $type
    ) {
        $hasOne = $propertyMetadata->getAnnotation(HasOne::class);
        $id     = $metadata->id->getValue($object);
        
        if ($hasOne instanceof HasOne && !empty($id)) {
            $data = $transferManager->getRepository($type)
                ->findOneBy([$hasOne->foreignField => $id]);

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
        $id      = $metadata->id->getValue($object);

        if ($hasMany instanceof HasMany && !empty($id)) {
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
