<?php

namespace Vox\Webservice\Proxy;

use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\TransferManagerInterface;

class ProxyFactory implements ProxyFactoryInterface
{
    /**
     * @var AccessInterceptorValueHolderFactory
     */
    private $accessInterceptorFactory;
    
    public function __construct()
    {
        $this->accessInterceptorFactory = new AccessInterceptorValueHolderFactory();
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
                $interceptors[$getter] = function () use ($metadata, $name, $object, $transferManager) {
                    /* @var $propertyMetadata PropertyMetadata */
                    $propertyMetadata = $metadata->propertyMetadata[$name];
                    $type             = $propertyMetadata->type;
                    
                    if (class_exists($type)) {
                        $belongsTo = $propertyMetadata->getAnnotation(BelongsTo::class);
                        
                        if ($belongsTo instanceof BelongsTo) {
                            $data = $transferManager
                                ->find($type, $metadata->propertyMetadata[$belongsTo->foreignField]->getValue($object));
                            
                            $propertyMetadata->setValue($object, $data);
                        }
                    }
                };
            }
        }
        
        return $this->accessInterceptorFactory->createProxy($object, $interceptors);
    }
}
