<?php

namespace Vox\Webservice;

use Doctrine\Common\Persistence\ObjectManager;
use Vox\Webservice\Metadata\TransferMetadata;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface TransferManagerInterface extends ObjectManager
{
    public function getClassMetadata($className): TransferMetadata;
    
    public function getUnitOfWork(): UnitOfWorkInterface;
}
