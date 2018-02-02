<?php

namespace Vox\Metadata;

use Metadata\PropertyMetadata as BaseMetadata;
use ProxyManager\Proxy\AccessInterceptorValueHolderInterface;
use ReflectionClass;

/**
 * Holds all metadata for a single property
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
class PropertyMetadata extends BaseMetadata
{
    use AnnotationsTrait;
    
    public $type;
    
    public $typeInfo;
    
    /**
     * @param ReflectionClass $class
     * @param string $name
     */
    public function __construct($class, $name)
    {
        parent::__construct($class, $name);
        
        $this->type     = $this->parseType();
        $this->typeInfo = $this->parseTypeDecoration($this->type);
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
    
    public function getParsedType()
    {
        if (isset($this->type)) {
            return preg_replace('/(\[\]$)|(\<\>$)/', '', $this->type);
        }
    }
    
    public function isNativeType(): bool
    {
        return in_array($this->type, [
            'string',
            'array',
            'int',
            'integer',
            'float',
            'boolean',
            'bool',
            'DateTime',
            '\DateTime',
            '\DateTimeImmutable',
            'DateTimeImmutable',
        ]);
    }
    
    public function isDecoratedType(): bool
    {
        return (bool) preg_match('/(.*)((\<(.*)\>)|(\[\]))/', $this->type);
    }
    
    public function isDateType(): bool
    {
        $type = $this->isDecoratedType() ? $this->typeInfo['class'] ?? $this->type : $this->type;
        
        return in_array($type, ['DateTime', '\DateTime', 'DateTimeImmutable', '\DateTimeImmutable']);
    }
    
    private function parseTypeDecoration(string $type = null)
    {
        if (preg_match('/(?P<class>.*)((\<(?P<decoration>.*)\>)|(?P<brackets>\[\]))/', $type, $matches)) {
            return [
                'class'      => isset($matches['brackets']) ? 'array' : $matches['class'],
                'decoration' => isset($matches['brackets']) ? $matches['class'] : $matches['decoration']
            ];
        }
    }
    
    public function serialize()
    {
        return serialize([
            $this->class,
            $this->name,
            $this->annotations,
            $this->type,
            $this->typeInfo,
        ]);
    }

    public function unserialize($str)
    {
        list($this->class, $this->name, $this->annotations, $this->type, $this->typeInfo) = unserialize($str);

        $this->reflection = new \ReflectionProperty($this->class, $this->name);
        $this->reflection->setAccessible(true);
    }
}
