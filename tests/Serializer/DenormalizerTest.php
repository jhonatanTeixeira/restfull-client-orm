<?php

namespace Vox\Serializer;

use Doctrine\Common\Annotations\AnnotationReader;
use Metadata\MetadataFactory;
use PHPUnit\Framework\TestCase;
use Vox\Data\ObjectHydrator;
use Vox\Metadata\Driver\AnnotationDriver;

class DenormalizerTest extends TestCase
{
    public function testShouldHydrate()
    {
        $data = [
            'dolor' => 'dolor',
            'ipsums' => [
                [
                    'sit' => 'sit'
                ],
                [
                    'sit' => 'amet'
                ],
            ]
        ];
        
        $dern = new Denormalizer(new ObjectHydrator(new MetadataFactory(new AnnotationDriver(new AnnotationReader()))));
        
        $lorem = $dern->denormalize($data, Lorem::class);
        
        $this->assertEquals('dolor', $lorem->getDolor());
        $this->assertEquals('sit', $lorem->getIpsums()[0]->getSit());
        $this->assertEquals('amet', $lorem->getIpsums()[1]->getSit());
    }
}


class Lorem
{
    private $dolor;
    
    /**
     * @var Ipsum[]
     */
    private $ipsums;
    
    public function getDolor()
    {
        return $this->dolor;
    }

    public function getIpsums(): array
    {
        return $this->ipsums;
    }
}

class Ipsum
{
    private $sit;
    
    public function getSit()
    {
        return $this->sit;
    }
}