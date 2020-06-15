<?php

namespace Larapackages\Repository\Eloquent\Exceptions;

use RuntimeException;
use Throwable;

class CacheStrategyNotFoundException extends RuntimeException
{
    /**
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct($code = 0, Throwable $previous = null)
    {
        parent::__construct('Cache strategy not found.', $code, $previous);
    }
}
