<?php

namespace Vox\Webservice;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface UnityOfWorkInterface extends ObjectStorageInterface
{
    public function remove($object);
    
    public function isNew($object): bool;
    
    public function isDirty($object): bool;
    
    public function isRemoved($object): bool;
    
    public function isDetached($object): bool;
}
