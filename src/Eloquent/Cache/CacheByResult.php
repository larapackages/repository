<?php

namespace Larapackages\Repository\Eloquent\Cache;

use Illuminate\Support\Facades\Cache;
use Larapackages\Repository\Eloquent\Repository;

final class CacheByResult implements CacheStrategy
{
    /**
     * @var mixed
     */
    private $repository;

    /**
     * @var int
     */
    private $cache_seconds;

    /**
     * @var array
     */
    private $cache_tags = [];

    /**
     * CacheById constructor.
     *
     * @param Repository $repository
     * @param int        $cache_seconds
     * @param array      $cache_tags
     */
    public function __construct(Repository $repository, int $cache_seconds, array $cache_tags = [])
    {
        $this->repository    = $repository;
        $this->cache_seconds = $cache_seconds;
        $this->cache_tags    = $cache_tags;
    }

    /**
     * Get cache key use
     *
     * @param $method
     * @param $args
     *
     * @return string
     */
    public function getCacheKey(string $method, array $args): string
    {
        $key = sprintf(
            'result:%s($s):%s_%s',
            $method,
            json_encode($args),
            $this->repository->getModel()->toSql(),
            json_encode($this->repository->getModel()->getBindings())
        );

        return md5($key);
    }

    /**
     * @param string   $method
     * @param array    $args
     * @param callable $default
     *
     * @return mixed
     */
    public function get(string $method, array $args, callable $default)
    {
        $cache_key = $this->getCacheKey($method, $args);
        $result    = Cache::tags($this->cache_tags)->get($cache_key);

        if ($result === null) {
            $result = $default($method, $args);
            $this->write($method, $args, $result);
        }

        return $result;
    }

    /**
     * @param string $method
     * @param array  $args
     * @param        $result
     */
    public function write(string $method, array $args, $result): void
    {
        Cache::tags($this->cache_tags)->put(
            $this->getCacheKey($method, $args),
            $result,
            $this->cache_seconds
        );
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        Cache::tags($this->cache_tags)->flush();
    }
}
