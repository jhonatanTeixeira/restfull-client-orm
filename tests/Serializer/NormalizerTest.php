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
            'othersArray' => null,
            'othersArray2' => null,
            'other' => [
                'name' => 'abcdfg',
                'last_name' => 'efg'
            ]
        ];
        
        $normalizer = new Normalizer(new MetadataFactory(new AnnotationDriver(new AnnotationReader())));
        
        $normalized = $normalizer->normalize(new Some());
        
        $this->assertEquals($normalized, $compare);
    }
    
    public function testShouldNormalizeCollection()
    {
        $compare = [
            'name' => 'abcd',
            'othersArray' => [
                [
                    'name' => 'aaaa'
                ],
                [
                    'name' => 'bbbb'
                ],
            ],
            'othersArray2' => [
                [
                    'name' => 'abcdfg',
                ],
                [
                    'name' => 'abcdfg'
                ],
            ],
            'other' => [
                'name' => 'abcdfg',
                'last_name' => 'efg'
            ]
        ];

        $normalizer = new Normalizer(new MetadataFactory(new AnnotationDriver(new AnnotationReader())));
        
        $some = new Some();
        $some->othersArray = [
            [
                'name' => 'aaaa'
            ],
            [
                'name' => 'bbbb'
            ],
        ];
        
        $some->othersArray2 = [
            new Other(),
            new Other(),
        ];
        
        $normalized = $normalizer->normalize($some);
        
        $this->assertEquals($normalized, $compare);
    }

    public function shouldNormalizeExtension()
    {
        $compare = [
            'name' => 'abcd',
            'type' => SomeExtension::class,
            'othersArray' => null,
            'othersArray2' => null,
            'otherName' => 'other-name',
            'other' => [
                'name' => 'abcdfg',
                'last_name' => 'efg'
            ]
        ];

        $normalizer = new Normalizer(new MetadataFactory(new AnnotationDriver(new AnnotationReader())));

        $normalized = $normalizer->normalize(new SomeExtension());

        $this->assertEquals($normalized, $compare);
    }
    
    public function testShouldNormalizeDecoratedDateTime()
    {
        $normalizer = new Normalizer(new MetadataFactory(new AnnotationDriver(new AnnotationReader())));
        
        $some = new SomeWithDateTime();
        $some->setDate(new \DateTime());
        
        $normalized = $normalizer->normalize($some);
        
        $this->assertEquals(date('Y-m-d'), $normalized['date']);
    }
}

class Some
{
    private $name = 'abcd';
    
    private $other;
    
    /**
     * @var array
     */
    public $othersArray;

    /**
     * @var Other[]
     */
    public $othersArray2;
    
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

class SomeExtension extends Some
{
    private $otherName = 'other-name';
}

class SomeWithDateTime
{
    /**
     * @var \DateTime<Y-m-d>
     */
    private $date;
    
    public function getDate()
    {
        return $this->date;
    }

    public function setDate(\DateTime $date)
    {
        $this->date = $date;
        return $this;
    }
}
