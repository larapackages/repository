<?php

namespace Larapackages\Repository\Eloquent\Exceptions;

use RuntimeException;
use Throwable;

class CanNotSpecifyOrderByClauseException extends RuntimeException
{
    /**
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct($code = 0, Throwable $previous = null)
    {
        parent::__construct('You can not specify an orderBy clause when using this function.', $code, $previous);
    }
}
