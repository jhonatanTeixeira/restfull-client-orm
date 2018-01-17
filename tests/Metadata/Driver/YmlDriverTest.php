<?php

namespace Vox\Metadata\Driver;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vox\Data\Mapping\Exclude;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Metadata\TransferMetadata;

class YmlDriverTest extends TestCase
{
    public function testShouldLoadYmlMetadata()
    {
        $ymlDriver = new YmlDriver(__DIR__ . '/../../fixtures/metadata', TransferMetadata::class);
        
        $metadata = $ymlDriver->loadMetadataForClass(new ReflectionClass(MetadataStub::class));
        
        $this->assertEquals($metadata->propertyMetadata['nome']->type, 'string');
        $this->assertEquals($metadata->propertyMetadata['email']->type, 'string');
        $this->assertEquals($metadata->propertyMetadata['id']->type, 'int');
        $this->assertEquals($metadata->propertyMetadata['additional']->type, 'int');
        $this->assertEquals($metadata->id->getName(), 'id');
        $this->assertArrayHasKey(Exclude::class, $metadata->propertyMetadata['additional']->annotations);
        $this->assertArrayHasKey(BelongsTo::class, $metadata->propertyMetadata['belongs']->annotations);
        $this->assertArrayHasKey(Exclude::class, $metadata->propertyMetadata['belongs']->annotations);
    }

    public function testShouldReadMultiIdMetadata()
    {
        $ymlDriver = new YmlDriver(__DIR__ . '/../../fixtures/metadata', TransferMetadata::class);

        $metadata = $ymlDriver->loadMetadataForClass(new ReflectionClass(MultiIdMetadataStub::class));

        $transfer = new MultiIdMetadataStub();

        $this->assertEquals('idOne=1;idTwo=1', $metadata->id->getValue($transfer));
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
    
    /**
     * @var int
     */
    private $additional = 123;
    
    /**
     * @var MetadataStub
     */
    private $belongs;
    
    public function getNome()
    {
        return $this->nome;
    }

    public function getEmail()
    {
        return $this->email;
    }
    
    public function getAdditional()
    {
        return $this->additional;
    }
    
    public function getId()
    {
        return $this->id;
    }

    public function getBelongs(): MetadataStub
    {
        return $this->belongs;
    }
}

class MultiIdMetadataStub
{
    /**
     * @var int
     */
    private $idOne = 1;

    /**
     * @var int
     */
    private $idTwo = 1;
}
