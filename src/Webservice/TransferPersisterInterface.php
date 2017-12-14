<?php

namespace Vox\Webservice;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface TransferPersisterInterface
{
    public function save($transfer);
}
