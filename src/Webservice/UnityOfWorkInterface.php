<?php

namespace Vox\Webservice;

interface UnityOfWorkInterface extends ObjectStorageInterface
{
    public function flush();
    
    public function isNew($object): bool;
    
    public function isDirty($object): bool;
    
    public function isDetached($object): bool;
}
