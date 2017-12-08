<?php

namespace Vox\Metadata;

use Metadata\PropertyMetadata as BaseMetadata;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ReflectionClass;

class PropertyMetadata extends BaseMetadata
{
    use AnnotationsTrait;
    
    public $type;
    
    /**
     * @param ReflectionClass $class
     * @param string $name
     */
    public function __construct($class, $name)
    {
        parent::__construct($class, $name);
        
        $this->type = $this->parseType();
    }
    
    public function getValue($obj)
    {
        if ($obj instanceof AccessInterceptorValueHolderInterface) {
            $obj = $obj->getWrappedValueHolderValue();
        }
        
        return parent::getValue($obj);
    }
    
    public function setValue($obj, $value)
    {
        if ($obj instanceof AccessInterceptorValueHolderInterface) {
            $obj = $obj->getWrappedValueHolderValue();
        }
        
        parent::setValue($obj, $value);
    }
    
    private function parseType()
    {
        $docComment = $this->reflection->getDocComment();
        
        preg_match('/@var (.*)/', $docComment, $matches);
        
        $type = $matches[1] ?? null;
        
        if (null === $type) {
            return;
        }
        
        $uses = $this->getClassUses();
        
        foreach ($uses as $use) {
            if (preg_match("/{$type}$/", $use)) {
                return $use;
            }
            
            if (class_exists("$use\\$type")) {
                return "$use\\$type";
            }
        }
        
        return $type;
    }
    
    private function getClassUses(): array
    {
        $filename = $this->reflection->getDeclaringClass()->getFileName();
        
        if (is_file($filename)) {
            $contents = file_get_contents($filename);
            
            preg_match_all('/use (.*);/', $contents, $matches);
            
            $uses = $matches[1] ?? [];
            
            $matches = [];
            
            preg_match('/namespace (.*);/', $contents, $matches);
            
            if (!empty($matches[1])) {
                array_push($uses, $matches[1]);
            }
            
            return $uses;
        }
        
        return [];
    }
}
