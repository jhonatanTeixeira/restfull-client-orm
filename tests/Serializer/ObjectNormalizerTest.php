<?php

namespace Vox\Serializer;

use Doctrine\Common\Annotations\AnnotationReader;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Serializer;
use Vox\Data\ObjectHydrator;
use Vox\Metadata\Driver\AnnotationDriver;

class ObjectNormalizerTest extends TestCase
{
    private $normalizer;
    
    private $denormalizer;
    
    private $objectNormalizer;

    private $serializer;
    
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

        $this->serializer = new Serializer([
            $this->objectNormalizer,
            new DateTimeNormalizer('Y-m-d H:i:s')
        ]);
    }
    
    public function testShouldNormalizeComplexType()
    {
        $compare = [
            'name' => 'abcd',
            'date' => date('Y-m-d H:i:s'),
            'other' => [
                'name' => 'abcdfg',
            ],
            'other_two' => [
                'name' => 'abcdfg',
            ]
        ];
        
        $normalized = $this->serializer->normalize(new SomeOne());
        
        $this->assertEquals($compare, $normalized);
    }

    public function testShouldDenormalize()
    {
        $data = [
            'name' => 'abcd',
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

    /**
     * @var \DateTime
     */
    private $date;
    
    public function __construct()
    {
        $this->other = new OtherTwo();
        $this->otherTwo = new OtherTwo();
        $this->date = new \DateTime();
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
