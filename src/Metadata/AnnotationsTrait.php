<?php

namespace Vox\Metadata;

trait AnnotationsTrait
{
    private $annotations;
    
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    public function setAnnotations(array $annotations)
    {
        $this->annotations = $annotations;
        
        return $this;
    }
    
    public function getAnnotation(string $annotationName, $throwException = false)
    {
        if (!isset($this->annotations[$annotationName]) && $throwException) {
            throw new \InvalidArgumentException("no annotation with name $annotationName");
        }
        
        return $this->annotations[$annotationName] ?? null;
    }
}
