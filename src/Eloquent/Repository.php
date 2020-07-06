<?php

namespace Larapackages\Repository\Eloquent;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\App;
use Larapackages\Repository\Eloquent\Cache\CacheById;
use Larapackages\Repository\Eloquent\Cache\CacheByResult;
use Larapackages\Repository\Eloquent\Cache\CacheStrategy;
use Larapackages\Repository\Eloquent\Exceptions\CacheStrategyNotFoundException;
use Larapackages\Repository\Eloquent\Exceptions\CanNotSpecifyOrderByClauseException;
use Larapackages\Repository\Eloquent\Exceptions\MethodNotFoundException;
use Larapackages\Repository\Eloquent\Exceptions\InvalidOperatorException;

/**
 * Class Repository
 *
 * @mixin EloquentBuilder
 * @mixin QueryBuilder
 * @mixin Model
 */
abstract class Repository
{
    /**
     * @var mixed
     */
    protected $model;

    /**
     * @var array
     */
    private $eloquent_getters = [
        'all',
        'avg',
        'count',
        'create',
        'cursor',
        'delete',
        'doesntExist',
        'exists',
        'find',
        'findOrFail',
        'first',
        'firstOrCreate',
        'firstOrFail',
        'get',
        'insert',
        'max',
        'min',
        'paginate',
        'pluck',
        'simplePaginate',
        'sum',
        'update',
        'updateOrCreate',
        'latest',
    ];

    /**
     * @var array
     */
    private $eloquent_availables = [
        'distinct',
        'forPage',
        'groupBy',
        'has',
        'limit',
        'orHas',
        'select',
        'skip',
        'take',
        'whereNotNull',
        'whereNull',
        'with',
        'withCount',
    ];

    /**
     * @var CacheStrategy|null
     */
    private $eloquent_cache = null;

    /**
     * AppRepository constructor.
     */
    public function __construct()
    {
        $this->makeModel();
    }

    /**
     * Get model
     *
     * @return mixed
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Make model
     *
     * @return $this
     */
    public function makeModel()
    {
        $this->model = App::make($this->model());

        return $this;
    }

    /**
     * Apply the callback's changes if the given "value" is true.
     *
     * @param mixed    $value
     * @param callable $callback
     * @param callable $default
     *
     * @return mixed
     */
    public function when($value, $callback, $default = null)
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * @param        $value
     * @param string $operator
     *
     * @return $this
     */
    public function filterByKey($value, string $operator = '=')
    {
        $key_name = $this->model->getModel()->getKeyName();

        if (!is_array($value) && !$value instanceof Arrayable) {
            $this->model = $this->model->where($key_name, $operator, $value);

            return $this;
        }

        if (!in_array($operator, ['=', '!='])) {
            throw new InvalidOperatorException($operator);
        }

        $this->model = $this->model->whereIn(
            $key_name,
            $value,
            'and',
            $operator === '!='
        );

        return $this;
    }

    /**
     * @param string $dir
     *
     * @return $this
     */
    public function orderByKey($dir = 'asc')
    {
        $key_name    = $this->model->getModel()->getKeyName();
        $this->model = $this->model->orderBy($key_name, $dir);

        return $this;
    }

    /**
     * Chunk the results of the query.
     *
     * @param int      $count
     * @param callable $callback
     *
     * @return bool
     */
    public function chunk(int $count, callable $callback): bool
    {
        if (empty($this->model->getQuery()->orders) && empty($this->model->getQuery()->unionOrders)) {
            $this->orderByKey('asc');
        }

        $page = 1;

        do {
            $results       = (clone $this)
                ->forPage($page, $count)
                ->get();
            $count_results = $results->count();

            if ($count_results == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            unset($results);


            $page++;
        } while ($count_results == $count);

        return true;
    }

    /**
     * Chunk the results of a query by comparing numeric IDs.
     *
     * @param int      $count
     * @param callable $callback
     *
     * @return bool
     */
    public function chunkById(int $count, callable $callback): bool
    {
        if (!empty($this->model->getQuery()->orders) || !empty($this->model->getQuery()->unionOrders)) {
            throw new CanNotSpecifyOrderByClauseException();
        }

        $last_id = 0;

        $repository = (clone $this)
            ->orderByKey()
            ->take($count);

        $key_name = $this->model->getModel()->getKeyName();

        do {
            $results       = (clone $repository)
                ->filterByKey($last_id, '>')
                ->get();
            $count_results = $results->count();

            if ($count_results > 0) {
                $last_id = $results->last()->{$key_name};
                if ($callback($results) === false) {
                    return false;
                }
            }

            unset($results);
        } while ($count_results == $count);

        return true;
    }

    /**
     * @return CacheStrategy|null
     */
    public function getCache(): ?CacheStrategy
    {
        return $this->eloquent_cache;
    }

    /**
     * @param int    $cache_seconds
     * @param array  $cache_tags
     * @param string $strategy
     *
     * @return $this
     */
    public function withCache(int $cache_seconds, array $cache_tags = [], string $strategy = 'id')
    {
        $cache_tags = array_merge([get_called_class()], $cache_tags);

        switch ($strategy) {
            case 'id':
                $this->eloquent_cache = new CacheById($this, $cache_seconds, $cache_tags);
                break;
            case 'result':
                $this->eloquent_cache = new CacheByResult($this, $cache_seconds, $cache_tags);
                break;
            default:
                throw new CacheStrategyNotFoundException;
                break;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutCache()
    {
        $this->eloquent_cache = null;

        return $this;
    }

    /**
     * Force a clone of the underlying model when cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->model = clone $this->model;
    }

    /**
     * Overloading __call.
     *
     * @param $method
     * @param $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        $available_eloquent_methods = array_merge($this->eloquent_getters, $this->eloquent_availables);

        if (!in_array($method, $available_eloquent_methods)) {
            throw (new MethodNotFoundException())->setMethod(get_called_class(), $method);
        }

        $args = empty($args) ? [] : $args;

        if (in_array($method, $this->eloquent_getters)) {
            $getter = function ($method, $args) {
                $model = call_user_func_array([$this->model, $method], $args);

                $this->makeModel();

                return $model;
            };

            if ($this->eloquent_cache === null) {
                return $getter($method, $args);
            }

            return $this->eloquent_cache->get($method, $args, $getter);
        }

        $this->model = call_user_func_array([$this->model, $method], $args);

        return $this;
    }

    /**
     * @return string
     */
    abstract protected function model();
}