<?php

namespace Vox\Webservice\Event;

use Doctrine\Common\Persistence\ObjectManager;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface EventInterface
{
    public function getObjectManager();
}
