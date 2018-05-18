<?php

namespace Vox\Webservice;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface TransactionInterface
{
    public function addCreated($object);
    
    public function addUpdated($object);
    
    public function addDeleted($object);
    
    public function rollback();
}
