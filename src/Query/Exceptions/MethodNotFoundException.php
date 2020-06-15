<?php

namespace Larapackages\Repository\Query\Exceptions;

use RuntimeException;

class MethodNotFoundException extends RuntimeException
{
    /**
     * @var string
     */
    protected $repository;

    /**
     * @var string
     */
    protected $method;

    /**
     * @param string $repository
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $repository, string $method)
    {
        $this->repository = $repository;
        $this->method     = $method;
        $this->message    = sprintf('Method %s does not exists in %s', $this->method, $this->repository);

        return $this;
    }

    /**
     * @return string
     */
    public function getRepository(): string
    {
        return $this->repository;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }
}
