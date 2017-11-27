<?php

namespace Vox\Webservice;

interface WebserviceClientInterface
{
    public function cGet(string $transferName, array $filters = []): TransferCollection;
    
    public function get(string $transferName, $id);
    
    public function post($transfer);
    
    public function put($transfer);
    
    public function delete(string $transferName, $id);
}
