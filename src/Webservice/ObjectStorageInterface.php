<?php

namespace Vox\Webservice;

use IteratorAggregate;

interface ObjectStorageInterface extends IteratorAggregate
{
    public function contains($object): bool;
    
    public function attach($object);
    
    public function detach($object);
    
    public function isEquals($object): bool;
}
