<?php

namespace Vox\Metadata\Driver;

use Metadata\Driver\DriverInterface;
use Metadata\MethodMetadata;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Yaml\Parser;
use Vox\Data\Mapping\Bindings;
use Vox\Data\Mapping\Exclude;
use Vox\Metadata\ClassMetadata;
use Vox\Metadata\PropertyMetadata;
use Vox\Webservice\Mapping\BelongsTo;
use Vox\Webservice\Mapping\Id;
use Vox\Webservice\Mapping\Resource;

/**
 * Yml driver to create a class metadata information
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
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
        if ($class->implementsInterface(AccessInterceptorValueHolderInterface::class)) {
            $class = $class->getParentClass();
        }
        
        $yml = $this->loadYml($class);
        
        /* @var $classMetadata ClassMetadata */
        $classMetadata = (new ReflectionClass($this->classMetadataClassName))->newInstance($class->name);
        
        if (isset($yml['resource'])) {
            $resource         = new Resource();
            $resource->client = $yml['resource']['client'] ?? null;
            $resource->route  = $yml['resource']['route'] ?? null;
            $classMetadata->setAnnotations([Resource::class => $resource]);
        }
        
        foreach ($class->getMethods() as $method) {
            $classMetadata->addMethodMetadata(new MethodMetadata($class->name, $method->name));
        }
        
        /* @var $reflectionProperty ReflectionProperty */
        foreach ($class->getProperties() as $reflectionProperty) {
            $annotations = [];
            $annotations[Bindings::class] = $bindings = new Bindings();
            
            if (isset($yml['parameters'][$reflectionProperty->name])) {
                $name             = $reflectionProperty->name;
                $config           = $yml['parameters'][$name];
                $bindings->source = $config['bindings']['source'] ?? null;
                $bindings->target = $config['bindings']['target'] ?? null;

                if ($name == $yml['id'] ?? null) {
                    $annotations[Id::class] = new Id();
                }
                
                if (isset($config['belongsTo'])) {
                    $belongsTo = new BelongsTo();
                    $belongsTo->foreignField = $config['belongsTo']['foreignField'];
                    $annotations[BelongsTo::class] = $belongsTo;
                }
                
                if (isset($config['exclude'])) {
                    $exclude = new Exclude();
                    $exclude->input  = $config['exlude']['input'] ?? true;
                    $exclude->output = $config['exlude']['output'] ?? true;
                    $annotations[Exclude::class] = $exclude;
                }
            }
            
            /* @var $propertyMetadata PropertyMetadata */
            $propertyMetadata = (new ReflectionClass($this->propertyMetadataClassName))
                ->newInstance($class->name, $reflectionProperty->name);
            
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
