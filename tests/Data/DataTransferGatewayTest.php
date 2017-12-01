<?php

namespace Vox\Data;

use Doctrine\Common\Annotations\AnnotationReader;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Vox\Data\Mapping\Bindings;
use Vox\Metadata\Driver\AnnotationDriver;

class DataTransferGatewayTest extends TestCase
{
    public function testShouldTransferData()
    {
        $metadataFactory  = new MetadataFactory(new AnnotationDriver(new AnnotationReader()));
        $propertyAccessor = new PropertyAccessor($metadataFactory);
        $graphBuilder     = new ObjectGraphBuilder($metadataFactory, $propertyAccessor);
        
        $gateway = new DataTransferGateway($graphBuilder, $metadataFactory, $propertyAccessor);
        
        $gatewayTest = new GatewayTestOne();
        
        $target = $gateway->transferData($gatewayTest, GatewayTargetOne::class);
    }
}

class GatewayTestOne
{
    /**
     * @Bindings(target="targetOne")
     */
    private $one = 'one';
    
    /**
     * @Bindings(target="targetTwo")
     * 
     * @var GatewayTestTwo
     */
    private $two;
    
    /**
     * @Bindings(target="targetThree.name")
     * 
     * @var string
     */
    private $three = 'three';
    
    public function __construct()
    {
        $this->two = new GatewayTestTwo();
    }
}

class GatewayTestTwo
{
    private $name = 'two';
    
    
    public function getName()
    {
        return $this->name;
    }
}

class GatewayTargetOne
{
    private $name = 'two';
    
    /**
     * @var GatewayTargetTwo
     */
    private $two;
    
    public function getName()
    {
        return $this->name;
    }
    
    public function getTwo(): GatewayTargetTwo
    {
        return $this->two;
    }
}

class GatewayTargetTwo
{
    private $name;
    
    /**
     * @var GatewayTargetTwo
     */
    private $two;
    
    /**
     * @var GatewayTargetThree
     */
    private $three;
    
    public function getName()
    {
        return $this->name;
    }
}

class GatewayTargetThree
{
    private $name;
    
    public function getName()
    {
        return $this->name;
    }
}
