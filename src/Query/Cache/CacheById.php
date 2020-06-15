<?php

namespace Larapackages\Repository\Query\Cache;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Larapackages\Repository\Query\Repository;

final class CacheById implements CacheStrategy
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
            'id:%s($s):%s_%s',
            $method,
            json_encode($args),
            $this->repository->getQuery()->toSql(),
            json_encode($this->repository->getQuery()->getBindings())
        );

        return md5($key);
    }

    /**
     * @inheritDoc
     */
    public function get(QueryBuilder $query, string $method, array $args, callable $default)
    {
        $cache_key = $this->getCacheKey($method, $args);
        $result    = Cache::tags($this->cache_tags)->get($cache_key);

        if ($result === null) {
            $result = $default($method, $args);
            $this->write($method, $args, $result);
        } elseif ($result === 'pks:') {
            $result = new EloquentCollection();
        } elseif ($result === 'null') {
            $result = null;
        } elseif (is_string($result) && Str::startsWith($result, 'pks:')) {
            $keys = str_replace('pks:', '', $result);
            $keys = explode(',', $keys);

            $this->repository->getQuery()->wheres = [];
            $this->repository->getQuery()->setBindings([], 'where');

            $this->repository->filterByKey($keys);

            if ($method === 'all') {
                $method = 'get';
            }

            $result = $default($query, $method, $args);
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
        $cache_result = null;

        if ($result === null) {
            $result = 'null';
        } elseif (is_object($result)) {
            $model_key = $this->repository->base_model->getKeyName();
            if (
                $result instanceof LazyCollection ||
                $result instanceof Collection ||
                $result instanceof AbstractPaginator
            ) {
                $cache_result = 'pks:';
                $cache_result .= $result->pluck($model_key)->implode(',');
            } elseif ($result instanceof Model) {
                $cache_result = 'pks:';
                $cache_result .= $result->{$model_key};
            }
        }

        Cache::tags($this->cache_tags)->put(
            $this->getCacheKey($method, $args),
            $cache_result ?: $result,
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
