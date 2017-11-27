<?php

namespace Vox\Webservice;

interface UnityOfWorkInterface extends ObjectStorageInterface
{
    public function flush();
}
