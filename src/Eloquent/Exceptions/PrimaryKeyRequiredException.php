<?php

namespace Larapackages\Repository\Eloquent\Exceptions;

use RuntimeException;
use Throwable;

class PrimaryKeyRequiredException extends RuntimeException
{
    /**
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct($code = 0, Throwable $previous = null)
    {
        parent::__construct('The primary key is required on queries with cache.', $code, $previous);
    }
}
