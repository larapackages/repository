<?php

namespace Larapackages\Repository\Eloquent\Exceptions;

use RuntimeException;

class InvalidOperatorException extends RuntimeException
{
    /**
     * @var string
     */
    protected $operator;

    /**
     * @param string $operator
     *
     * @return $this
     */
    public function setOperator(string $operator)
    {
        $this->operator = $operator;
        $this->message  = sprintf('Operator %s is invalid.', $this->operator);

        return $this;
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }
}
