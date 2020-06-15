<?php

namespace Larapackages\Repository\Eloquent\Cache;

/**
 * Interface CacheStrategy
 *
 * @package Larapackages\Repository\Eloquent\Cache
 */
interface CacheStrategy
{
    /**
     * @param string   $method
     * @param array    $args
     * @param callable $default
     *
     * @return mixed
     */
    public function get(string $method, array $args, callable $default);

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