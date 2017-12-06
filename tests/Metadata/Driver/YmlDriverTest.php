<?php

namespace Vox\Metadata\Driver;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class YmlDriverTest extends TestCase
{
    public function testShouldLoadYmlMetadata()
    {
        $ymlDriver = new YmlDriver(__DIR__ . '/../../fixtures/metadata');
        
        $metadata = $ymlDriver->loadMetadataForClass(new ReflectionClass(MetadataStub::class));
        
        $this->assertEquals($metadata->propertyMetadata['nome']->type, 'string');
        $this->assertEquals($metadata->propertyMetadata['email']->type, 'string');
        $this->assertEquals($metadata->propertyMetadata['id']->type, 'int');
    }
}

class MetadataStub
{
    /**
     * @var int
     */
    private $id;
    
    /**
     * @var string
     */
    private $nome;
    
    /**
     * @var string
     */
    private $email;
    
    public function getNome()
    {
        return $this->nome;
    }

    public function getEmail()
    {
        return $this->email;
    }
}
