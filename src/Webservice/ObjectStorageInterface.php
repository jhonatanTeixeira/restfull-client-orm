<?php

namespace Vox\Webservice;

use IteratorAggregate;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface ObjectStorageInterface extends IteratorAggregate
{
    public function contains($object): bool;
    
    public function attach($object);
    
    public function detach($object);
    
    public function isEquals($object): bool;

    public function fetchByParams(...$params);

    public function getOriginalObject($object);

}
