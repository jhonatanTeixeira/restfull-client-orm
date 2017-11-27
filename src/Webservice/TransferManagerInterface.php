<?php

namespace Vox\Webservice;

interface TransferManagerInterface
{
    public function persist($transfer);
    
    public function flush($transfer = null);
    
    public function find(string $tranferClassName, $id);
    
    public function findBy(string $tranferClassName, array $params);
}
