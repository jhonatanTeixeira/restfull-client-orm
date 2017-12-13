<?php

namespace Vox\Serializer;

use Doctrine\Common\Annotations\AnnotationReader;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Vox\Data\ObjectHydrator;
use Vox\Metadata\Driver\AnnotationDriver;

class ObjectNormalizerTest extends TestCase
{
    private $normalizer;
    
    private $denormalizer;
    
    private $objectNormalizer;
    
    protected function setUp()
    {
        $this->normalizer = $normalizer = new Normalizer(
            $metadataFactory = new MetadataFactory(new AnnotationDriver(new AnnotationReader()))
        );
        
        $this->denormalizer     = $denormalizer = new Denormalizer(new ObjectHydrator($metadataFactory));
        $this->objectNormalizer = new ObjectNormalizer(
            $normalizer,
            $denormalizer,
            null,
            new CamelCaseToSnakeCaseNameConverter()
        );
    }
    
    public function testShouldNormalizeComplexType()
    {
        $compare = [
            'name' => 'abcd',
            'type' => SomeOne::class,
            'other' => [
                'type' => OtherTwo::class,
                'name' => 'abcdfg',
            ],
            'other_two' => [
                'type' => OtherTwo::class,
                'name' => 'abcdfg',
            ]
        ];
        
        $normalized = $this->objectNormalizer->normalize(new SomeOne());
        
        $this->assertEquals($compare, $normalized);
    }

    public function testShouldDenormalize()
    {
        $data = [
            'name' => 'abcd',
            'type' => SomeOne::class,
            'other' => [
                'type' => OtherTwo::class,
                'name' => 'abcdfgh',
            ],
            'other_two' => [
                'type' => OtherTwo::class,
                'name' => 'abcdfgh',
            ]
        ];
        
        $someOne = $this->objectNormalizer->denormalize($data, SomeOne::class);
        
        $this->assertEquals('abcd', $someOne->getName());
        $this->assertEquals('abcdfgh', $someOne->getOther()->getName());
        $this->assertEquals('abcdfgh', $someOne->getOtherTwo()->getName());
    }
}

class SomeOne
{
    private $name = 'abcd';
    
    /**
     * @var OtherTwo
     */
    private $other;
    
    /**
     * @var OtherTwo
     */
    private $otherTwo;
    
    public function __construct()
    {
        $this->other = new OtherTwo();
        $this->otherTwo = new OtherTwo();
    }
    
    public function getName()
    {
        return $this->name;
    }

    public function getOther()
    {
        return $this->other;
    }
    
    public function getOtherTwo()
    {
        return $this->otherTwo;
    }
        
    public function getLastName()
    {
        return $this->lastName;
    }
}

class OtherTwo
{
    private $name = 'abcdfg';
    
    public function getName()
    {
        return $this->name;
    }
}
