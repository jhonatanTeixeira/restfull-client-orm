<?php

namespace Vox\Webservice\Event;

final class PersistenceEvents
{
    const PRE_FLUSH    = 'preFlush';

    const PRE_PERSIST  = 'prePersist';
    
    const PRE_UPDATE   = 'preUpdate';
    
    const PRE_REMOVE   = 'preRemove';

    const POST_PERSIST = 'postPersist';

    const POST_UPDATE  = 'postUpdate';
    
    const POST_REMOVE  = 'postRemove';

    const POST_FLUSH   = 'postFlush';

    const ON_LOAD      = 'onLoad';
    
    const ON_EXCEPTION = 'onException';

    private function __construct()
    {
        
    }
}
