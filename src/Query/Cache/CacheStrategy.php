<?php

namespace Larapackages\Repository\Query\Cache;

use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Interface CacheStrategy
 *
 * @package Larapackages\Repository\Query\Cache
 */
interface CacheStrategy
{
    /**
     * @param QueryBuilder $query
     * @param string       $method
     * @param array        $args
     * @param callable     $default
     *
     * @return mixed
     */
    public function get(QueryBuilder $query, string $method, array $args, callable $default);

    /**
     * @param string $method
     * @param array  $args
     * @param        $result
     *
     * @return void
     */
    public function write(string $method, array $args, $result): void;

    /**
     * @return void
     */
    public function flush(): void;
}