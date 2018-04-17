<?php

namespace Vox\Webservice;

use Doctrine\Common\EventManager;

class EventDispatcher extends EventManager implements EventDispatcherInterface
{
    public function addListener($event, $listener)
    {
        $this->addEventListener($event, $listener);
        
        return $this;
    }

    public function addSubscriber($subscriber)
    {
        $this->addEventSubscriber($subscriber);
        
        return $this;
    }

    public function dispatch(string $event, $context)
    {
        $this->dispatchEvent($event, $context);
    }
}
