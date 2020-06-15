<?php

namespace Larapackages\Repository\Query\Exceptions;

use RuntimeException;
use Throwable;

class MissingOrderByClauseException extends RuntimeException
{
    /**
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct($code = 0, Throwable $previous = null)
    {
        parent::__construct('You must specify an orderBy clause when using this function.', $code, $previous);
    }
}
