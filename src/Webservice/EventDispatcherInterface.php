<?php

namespace Vox\Webservice;

/**
 * Defines the commom interface for event handling on this package
 * 
 * @author Jhonatan Teixeira <jhonatan.teixeira@gmail.com>
 */
interface EventDispatcherInterface
{
    public function addListener($event, $listener);
    
    public function addSubscriber($subscriber);
    
    public function dispatch(string $event, $context);
}
