<?php

namespace Vox\Serializer;

use Doctrine\Common\Annotations\AnnotationReader;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Vox\Data\Mapping\Bindings;
use Vox\Metadata\Driver\AnnotationDriver;

class NormalizerTest extends TestCase
{
    public function testShouldNormalizeComplexType()
    {
        $compare = [
            'name' => 'abcd',
            'type' => Some::class,
            'other' => [
                'type' => Other::class,
                'name' => 'abcdfg',
                'last_name' => 'efg'
            ]
        ];
        
        $normalizer = new Normalizer(new MetadataFactory(new AnnotationDriver(new AnnotationReader())));
        
        $normalized = $normalizer->normalize(new Some());
        
        $this->assertEquals($normalized, $compare);
    }
}

class Some
{
    private $name = 'abcd';
    
    private $other;
    
    /**
     * @Bindings(target="other.last_name")
     */
    private $lastName = 'efg';
    
    public function __construct()
    {
        $this->other = new Other();
    }
    
    public function getName()
    {
        return $this->name;
    }

    public function getOther()
    {
        return $this->other;
    }
    
    public function getLastName()
    {
        return $this->lastName;
    }
}

class Other
{
    private $name = 'abcdfg';
}
