<?php

namespace Vox\Webservice\Event;

/**
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface LifecycleEventInterface extends EventInterface
{
    public function getObject();
}
