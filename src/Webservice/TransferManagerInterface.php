<?php

namespace Vox\Webservice;

use Doctrine\Common\Persistence\ObjectManager;
use Vox\Webservice\Metadata\TransferMetadata;

interface TransferManagerInterface extends ObjectManager
{
    public function getClassMetadata($className): TransferMetadata;
}
