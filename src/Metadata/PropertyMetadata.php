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
        
        preg_match('/@var\s+(([^\[\]\s]+)(\[\])?)/', $docComment, $matches);
        
        $fullType = $matches[1] ?? null;
        $type     = $matches[2] ?? null;
        
        if (null === $type) {
            return;
        }
        
        $uses = $this->getClassUses();
        
        foreach ($uses as $use) {
            if (preg_match("/{$type}$/", $use)) {
                return $use . ($matches[3] ?? null);
            }
            
            if (class_exists("$use\\$type")) {
                return "$use\\$type" . ($matches[3] ?? null);
            }
        }
        
        return $fullType;
    }
    
    private function getClassUses(): array
    {
        $filename = $this->reflection->getDeclaringClass()->getFileName();
        
        if (is_file($filename)) {
            $contents = file_get_contents($filename);
            
            preg_match_all('/use\s+(.*);/', $contents, $matches);
            
            $uses = $matches[1] ?? [];
            
            $matches = [];
            
            preg_match('/namespace\s+(.*);/', $contents, $matches);
            
            if (!empty($matches[1])) {
                array_push($uses, $matches[1]);
            }
            
            return $uses;
        }
        
        return [];
    }
    
    public function serialize()
    {
        return serialize(array(
            $this->class,
            $this->name,
            $this->annotations,
            $this->type,
        ));
    }

    public function unserialize($str)
    {
        list($this->class, $this->name, $this->annotations, $this->type) = unserialize($str);

        $this->reflection = new \ReflectionProperty($this->class, $this->name);
        $this->reflection->setAccessible(true);
    }
}
