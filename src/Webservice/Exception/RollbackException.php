<?php

namespace Vox\Webservice\Exception;

use Exception;

class RollbackException extends Exception
{
    public function __construct(\Throwable $previous)
    {
        parent::__construct("transaction could not be rolled back: {$previous->getMessage()}", 0, $previous);
    }
}
