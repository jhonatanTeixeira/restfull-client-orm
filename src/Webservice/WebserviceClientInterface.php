<?php

namespace Vox\Webservice;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface WebserviceClientInterface
{
    public function cGet(string $transferName, array $filters = []): TransferCollection;
    
    public function get(string $transferName, $id);
    
    public function post($transfer);
    
    public function put($transfer);
    
    public function delete(string $transferName, $id);

    public function getByCriteria(CriteriaInterface $criteria, string $transferName);
}
