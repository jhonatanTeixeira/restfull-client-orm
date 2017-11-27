<?php

namespace Vox\Webservice;

interface TransferPersisterInterface
{
    public function save($transfer);
    
    public function load(string $transferName, array $data);
}
