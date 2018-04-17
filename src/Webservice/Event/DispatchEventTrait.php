<?php

namespace Vox\Webservice\Event;

use Vox\Webservice\EventDispatcherInterface;

trait DispatchEventTrait
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;
    
    private function dispatchEvent(string $event, EventInterface $context = null)
    {
        if (!$this->eventDispatcher) {
            return;
        }
        
        $this->eventDispatcher->dispatch($event, $context);
    }
}
