<?php

namespace Vox\Metadata\Driver;

use Metadata\Driver\DriverInterface;
use Metadata\MethodMetadata;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Yaml\Parser;
use Vox\Data\Mapping\Bindings;
use Vox\Metadata\ClassMetadata;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Mapping\Resource;

class YmlDriver implements DriverInterface
{
    private $ymlParser;
    
    private $path;
    
    private $classMetadataClassName;
    
    private $propertyMetadataClassName;
    
    public function __construct(
        string $path,
        string $classMetadataClassName = ClassMetadata::class,
        string $propertyMetadataClassName = PropertyMetadata::class
    ) {
        $this->ymlParser                 = new Parser();
        $this->path                      = realpath($path);
        $this->classMetadataClassName    = $classMetadataClassName;
        $this->propertyMetadataClassName = $propertyMetadataClassName;
    }
    
    public function loadMetadataForClass(ReflectionClass $class): ClassMetadata
    {
        $yml = $this->loadYml($class);
        
        /* @var $classMetadata ClassMetadata */
        $classMetadata = (new ReflectionClass($this->classMetadataClassName))->newInstance($class->name);
        
        if (isset($yml['resource'])) {
            $resource = new Resource();
            $resource->client = $yml['resource']['client'] ?? null;
            $resource->route = $yml['resource']['route'] ?? null;
            $classMetadata->setAnnotations([Resource::class => $resource]);
        }
        
        foreach ($class->getMethods() as $method) {
            $classMetadata->addMethodMetadata(new MethodMetadata($class->name, $method->name));
        }
        
        foreach ($yml['parameters'] ?? [] as $name => $config) {
            $annotations = [];
            $annotations[Bindings::class] = $bindings = new Bindings();
            $bindings->source = $config['bindings']['source'] ?? null;
            $bindings->target = $config['bindings']['target'] ?? null;
            
            if ($name == $yml['id'] ?? null) {
                $annotations[Id::class] = new Id();
            }
            
            /* @var $propertyMetadata PropertyMetadata */
            $propertyMetadata = (new ReflectionClass($this->propertyMetadataClassName))
                ->newInstance($class->name, $name);
            
            $propertyMetadata->setAnnotations($annotations);
            
            $classMetadata->addPropertyMetadata($propertyMetadata);
        }
        
        return $classMetadata;
    }
    
    private function loadYml(ReflectionClass $class)
    {
        $className = $class->getName();
        
        $path = sprintf(
            '%s/%s.yml', 
            preg_replace('/\/$/', '', $this->path), 
            str_replace('\\', DIRECTORY_SEPARATOR, $className)
        );
        
        if (is_file($path)) {
            return $this->ymlParser->parse(file_get_contents($path));
        }
        
        $path = sprintf(
            '%s/%s.yml', 
            preg_replace('/\/$/', '', $this->path), 
            str_replace('\\', '.', $className)
        );
        
        if (is_file($path)) {
            return $this->ymlParser->parse(file_get_contents($path));
        }
        
        throw new RuntimeException("metadata file not found for class $className");
    }
}
