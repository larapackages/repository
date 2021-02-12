<?php

namespace Larapackages\Repository\Query;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Larapackages\Repository\Query\Cache\CacheById;
use Larapackages\Repository\Query\Cache\CacheByResult;
use Larapackages\Repository\Query\Cache\CacheStrategy;
use Larapackages\Repository\Query\Exceptions\CacheStrategyNotFoundException;
use Larapackages\Repository\Query\Exceptions\CanNotSpecifyOrderByClauseException;
use Larapackages\Repository\Query\Exceptions\InvalidOperatorException;
use Larapackages\Repository\Query\Exceptions\MethodNotFoundException;

/**
 * Class Repository
 *
 * @mixin QueryBuilder
 * @method \Illuminate\Database\Eloquent\Collection get(string|array $columns = ['*'])
 */
abstract class Repository
{
    /**
     * @var array
     */
    protected $query_getters = [
        'avg',
        'cursor',
        'delete',
        'doesntExist',
        'exists',
        'first',
        'get',
        'insert',
        'max',
        'min',
        'paginate',
        'pluck',
        'simplePaginate',
        'sum',
        'update',
    ];

    /**
     * @var array
     */
    private $query_availables = [
        'distinct',
        'forPage',
        'groupBy',
        'orderBy',
        'limit',
        'select',
        'skip',
        'take',
        'whereNotNull',
        'whereNull',
    ];

    /**
     * @var array
     */
    protected $eloquent_getters_hydrate = [
        'first',
        'get',
        'paginate',
        'simplePaginate',
    ];

    /**
     * @var Model
     */
    public $base_model;

    /**
     * @var QueryBuilder
     */
    protected $query;

    /**
     * On hydrate will load missing relationships
     *
     * @var array
     */
    protected $relationships = [];

    /**
     * @var array
     */
    private $information_schema = [];

    /**
     * @var array
     */
    private $with = [];

    /**
     * @var array
     */
    private $with_first = [];

    /**
     * @var array
     */
    private $joins = [];

    /**
     * @var array
     */
    private $models_aliases = [];

    /**
     * @var string
     */
    private $hydrate = null;

    /**
     * @var array
     */
    private $models_tables = [];

    /**
     * @var CacheStrategy|null
     */
    private $query_cache = null;

    /**
     * AppRepository constructor.
     */
    public function __construct()
    {
        $base_model       = $this->baseModel();
        $this->base_model = with(new $base_model);
        $this->init();
    }

    /**
     * Get query
     *
     * @return QueryBuilder
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string $model
     *
     * @return $this
     */
    public function hydrate(string $model)
    {
        $this->hydrate = $model;

        return $this;
    }

    /**
     * @param string $join_key
     * @param string $model_relation_method
     *
     * @return $this
     */
    public function with(string $join_key, string $model_relation_method)
    {
        if (!Arr::has($this->with, $join_key)) {
            $this->with[$join_key] = $model_relation_method;
            $this->with_first      = array_diff_key($this->with_first, $this->with);
        }

        return $this;
    }

    /**
     * @param string $join_key
     * @param string $model_relation_method
     *
     * @return $this
     */
    public function withFirst(string $join_key, string $model_relation_method)
    {
        if (!Arr::has($this->with_first, $join_key)) {
            $this->with_first[$join_key] = $model_relation_method;
            $this->with_first            = array_diff_key($this->with_first, $this->with);
        }

        return $this;
    }

    /**
     * @param string $model
     * @param string $alias
     *
     * @return $this
     */
    public function addModelAlias(string $model, string $alias)
    {
        if (!array_key_exists($alias, $this->models_aliases)) {
            $this->models_aliases[$alias] = $model;
        }

        return $this;
    }

    /**
     * @param string      $column
     * @param string|null $model
     *
     * @return string
     */
    public function column(string $column, string $model = null)
    {
        $table = $model ?? $this->baseModel();

        if (is_null($model) || !array_key_exists($model, $this->models_aliases)) {
            $table = $this->table($model);
        }

        return $table . '.' . $column;
    }

    /**
     * @param string      $column
     * @param string|null $model
     *
     * @return string
     */
    public function rawColumn(string $column, string $model = null)
    {
        return DB::raw($this->column($column, $model));
    }

    /**
     * @param string|null $model
     *
     * @return string
     */
    public function table(string $model = null): string
    {
        $model = $model ?? $this->baseModel();

        if (!key_exists($model, $this->models_tables)) {
            $with = Arr::get($this->models_aliases, $model, $model);

            $model_table = with(new $with)->getTable();
            if ($with != $model) {
                $model_table .= ' as ' . $model;
            }
            $this->models_tables[$model] = $model_table;
        }

        return $this->models_tables[$model];
    }

    /**
     * @param        $value
     * @param string $operator
     *
     * @return $this
     */
    public function filterByKey($value, string $operator = '=')
    {
        $column = $this->base_model->getQualifiedKeyName();

        return $this->applyCommonFilter($column, $value, $operator);
    }

    /**
     * @param string $dir
     *
     * @return $this
     */
    public function orderByKey(string $dir = 'asc')
    {
        $this->query = $this->query->orderBy($this->base_model->getQualifiedKeyName(), $dir);

        return $this;
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
                $this->query_cache = new CacheById($this, $cache_seconds, $cache_tags);
                break;
            case 'result':
                $this->query_cache = new CacheByResult($this, $cache_seconds, $cache_tags);
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
        $this->query_cache = null;

        return $this;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        $repository = clone $this;

        $key_name = $repository->base_model->getQualifiedKeyName();

        $repository
            ->filterByNotDeleted($repository->getQuery())
            ->groupBy($key_name)
            ->select($key_name);

        $result = DB::connection($this->base_model->getConnectionName())->query()->selectRaw(
            sprintf('COUNT(*) as aggregate FROM (%s) as query', $repository->query->toSql()),
            $repository->query->getBindings()
        )->value('aggregate');

        $this->init();

        return $result;
    }

    /**
     * Do callback while pending results
     * WARNING: Be sure that query results change on each iteration
     *
     * @param int      $count
     * @param callable $callback
     *
     * @return bool
     */
    public function doWhile(int $count, callable $callback): bool
    {
        $repository = (clone $this)->limit($count);

        do {
            $results       = (clone $repository)->get();
            $count_results = $results->count();

            if ($count_results == 0) {
                break;
            }

            if ($callback($results, $this) === false) {
                return false;
            }

            unset($results);
        } while ($count_results == $count);

        return true;
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
        if (empty($this->query->orders) && empty($this->query->unionOrders)) {
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
                $this->init();

                return false;
            }

            unset($results);


            $page++;
        } while ($count_results == $count);

        $this->init();

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
        if (!empty($this->query->orders) || !empty($this->query->unionOrders)) {
            throw new CanNotSpecifyOrderByClauseException();
        }

        $last_id = 0;

        $repository = (clone $this)
            ->orderByKey()
            ->take($count);

        $key_name           = $repository->base_model->getKeyName();

        do {
            $results       = (clone $repository)
                ->filterByKey($last_id, '>')
                ->get();
            $count_results = $results->count();

            if ($count_results > 0) {
                $last_id = $results->last()->{$key_name};
                if ($callback($results) === false) {
                    $this->init();

                    return false;
                }
            }

            unset($results);
        } while ($count_results == $count);

        $this->init();

        return true;
    }

    /**
     * @param array $columns
     *
     * @return mixed
     */
    public function firstOrFail($columns = ['*'])
    {
        $result = $this->first($columns);

        if (is_null($result)) {
            throw (new ModelNotFoundException)->setModel($this->hydrate ? $this->hydrate : $this->baseModel());
        }

        return $result;
    }

    /**
     * Force a clone of the underlying query when cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }

    /**
     * Overloading __call.
     *
     * @param mixed $method
     * @param       $args
     *
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        $available_eloquent_methods = array_merge($this->query_getters, $this->query_availables);

        if (!in_array($method, $available_eloquent_methods)) {
            throw (new MethodNotFoundException())->setMethod(get_called_class(), $method);
        }

        $args = empty($args) ? [] : $args;

        if (in_array($method, $this->query_getters)) {
            $result = null;

            if (in_array($method, $this->eloquent_getters_hydrate)) {
                $result = $this->callHydrateGetter($this->query, $method, $args);
            } else {
                $result = $this->callGetter($this->query, $method, $args);
            }

            $this->init();

            return $result;
        }

        $this->query = call_user_func_array([$this->query, $method], $args);

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
     * @param string          $related_model
     * @param string|callable $base_key
     * @param string|null     $related_key
     * @param string|null     $base_model
     * @param string|null     $type
     *
     * @return $this
     */
    final public function join(
        string $related_model,
        $base_key,
        string $related_key = null,
        string $base_model = null,
        string $type = null
    ) {
        $is_closure = $base_key instanceof Closure;
        $base_model = is_null($base_model) && $is_closure ? $related_key : $base_model;
        $join_key   = ($base_model ?? $this->baseModel()) . '->' . $related_model;

        if (!in_array($join_key, $this->joins)) {
            $this->joins[] = $join_key;

            if ($is_closure) {
                $closure = function (JoinClause $join) use ($base_key, $related_model) {
                    $base_key($join);

                    $model = Arr::get($this->models_aliases, $related_model, $related_model);

                    if (in_array(SoftDeletes::class, class_uses($model))) {
                        $join->whereNull($this->column('deleted_at', $related_model));
                    }
                };

                $this->query = $this->query->join($this->table($related_model), $closure, null, null, $type);

                return $this;
            }

            $closure = function (JoinClause $join) use ($base_key, $base_model, $related_key, $related_model) {
                $join->on($this->column($base_key, $base_model), '=', $this->column($related_key, $related_model));

                $model = Arr::get($this->models_aliases, $related_model, $related_model);

                if (in_array(SoftDeletes::class, class_uses($model))) {
                    $join->whereNull($this->column('deleted_at', $related_model));
                }
            };

            $this->query = $this->query->join($this->table($related_model), $closure, null, null, $type);
        }

        return $this;
    }

    /**
     * @param string      $related_model
     * @param             $base_key
     * @param string|null $related_key
     * @param string|null $base_model
     *
     * @return $this
     */
    final public function leftJoin(
        string $related_model,
        $base_key,
        string $related_key = null,
        string $base_model = null
    ) {
        return $this->join($related_model, $base_key, $related_key, $base_model, 'left');
    }

    /**
     * @param string      $related_model
     * @param             $base_key
     * @param string|null $related_key
     * @param string|null $base_model
     *
     * @return $this
     */
    final public function rightJoin(
        string $related_model,
        $base_key,
        string $related_key = null,
        string $base_model = null
    ) {
        return $this->join($related_model, $base_key, $related_key, $base_model, 'right');
    }

    /**
     * Init query builder and set model info
     *
     * @return $this
     */
    protected function init()
    {
        $this->with           = [];
        $this->joins          = [];
        $this->models_aliases = [];
        $this->hydrate        = null;
        $this->models_tables  = [$this->baseModel() => $this->base_model->getTable()];
        $this->query          = DB::connection($this->base_model->getConnectionName())
            ->table($this->base_model->getTable());
        $this->query_cache    = null;

        return $this;
    }

    /**
     * @param        $column
     * @param        $value
     * @param string $operator
     *
     * @return $this
     */
    protected function applyCommonFilter($column, $value, $operator = '=')
    {
        if (!is_array($value) && !$value instanceof Arrayable) {
            $this->query = $this->query->where($column, $operator, $value);

            return $this;
        }

        if (!in_array($operator, ['=', '!='])) {
            throw new InvalidOperatorException($operator);
        }

        $this->query = $this->query->where(function(QueryBuilder $query) use ($column, $value, $operator) {
            $value = $value instanceof Arrayable ? $value->toArray() : $value;

            if (($key = array_search(null, $value)) !== false) {
                unset($value[$key]);

                $query->orWhereNull($column);
            }

            $query->orWhereIn(
                $column,
                $value,
                'and',
                $operator === '!='
            );
        });

        return $this;
    }

    /**
     * @return string
     */
    abstract protected function baseModel(): string;

    /**
     * Get a lazy collection for the given query.
     *
     * @return LazyCollection
     */
    public function cursor(): LazyCollection
    {
        //Get the model to hydrate
        $model_hydrate = get_class($this->base_model);
        $model = with(new $model_hydrate);

        $this->filterByNotDeleted($this->query);
        $this->query->groupBy($this->column($model->getKeyName(), $model_hydrate));

        if (empty($this->query->columns)) {
            $this->query->select($this->column('*', $model_hydrate));
        }

        return $this->query->cursor();
    }

    /**
     * Call eloquent getter and return the model.
     *
     * @param QueryBuilder $query
     * @param mixed        $method
     * @param              $args
     *
     * @return mixed
     */
    private function callGetter(QueryBuilder $query, $method, $args)
    {
        $getter = function ($query, $method, $args) {
            $this->filterByNotDeleted($query);

            return call_user_func_array([$query, $method], $args);
        };

        if ($this->query_cache === null) {
            return $getter($query, $method, $args);
        }

        return $this->query_cache->get($query, $method, $args, $getter);
    }

    /**
     * Get query builder result and hydrate it and relationships to laravel Model/s
     *
     * @param QueryBuilder $query
     * @param              $method
     * @param              $args
     * @param null         $model_hydrate
     *
     * @return mixed
     */
    private function callHydrateGetter(QueryBuilder $query, $method, $args, $model_hydrate = null)
    {
        $cloned_query = clone $query;

        //Get the model to hydrate
        if (is_null($model_hydrate)) {
            $model_hydrate = $this->hydrate ? $this->hydrate : get_class($this->base_model);
        }
        $model = Arr::has($this->models_aliases, $model_hydrate) ?
            with(new $this->models_aliases[$model_hydrate]) :
            with(new $model_hydrate);

        //Prevent duplicated hydrated models when performing joins
        if (!empty($this->joins)) {
            $cloned_query->groupBy($this->column($model->getKeyName(), $model_hydrate));
        }

        $is_select_columns_model = empty($this->with_first);

        $cloned_query->select(
            $is_select_columns_model ?
                $this->column('*', $model_hydrate) :
                $this->getColumnsFromInformationSchemaToSelect()
        );

        $result = $this->callGetter($cloned_query, $method, $args);

        if (is_null($result)) {
            return null;
        }

        $result = $this->processResultToHydrate($query, $args, $model_hydrate, $result, $model,
            $is_select_columns_model);

        if (!empty($this->relationships) && $model_hydrate === get_class($this->base_model)) {
            $result->loadMissing($this->relationships);
        }

        return $result;
    }

    /**
     * Get tables columns from base table, with relationships and with_first relationships and return flatten columns
     * Response example: [ 'jet_accounts.id as jet_accounts_id', 'app_users.id as app_users_id', ... ]
     *
     * @return array
     */
    private function getColumnsFromInformationSchemaToSelect()
    {
        $base_model_table = $this->base_model->getTable();

        $tables = collect(array_merge($this->with, $this->with_first))
            ->diffKeys($this->information_schema)
            ->keys()
            ->filter(function ($relation) {
                return collect($this->joins)->filter(function ($join) use ($relation) {
                        return Str::endsWith($join, '->' . $relation);
                    })->count() > 0;
            })
            ->mapWithKeys(function ($relation) {
                $model = Arr::has($this->models_aliases, $relation) ?
                    with(new $this->models_aliases[$relation]) :
                    with(new $relation);

                $relation_name = class_exists($relation) ? with(new $relation)->getTable() : $relation;

                return [$relation_name => Schema::getColumnListing($model->getTable())];
            });

        if (!isset($this->information_schema[$base_model_table])) {
            $tables = $tables->put($base_model_table, Schema::getColumnListing($base_model_table));
        }

        if ($tables->count() > 0) {
            $tables->transform(function ($columns, $key) {
                return collect($columns)->transform(function ($column) use ($key) {
                    return $key . '.' . $column . ' as ' . $key . '_' . $column;
                });
            });

            $this->information_schema = array_merge($this->information_schema, $tables->toArray());
        }

        return collect($this->information_schema)->flatten()->toArray();
    }

    /**
     * Transform query builder result to Model or EloquentCollection
     *
     * @param QueryBuilder $query
     * @param              $args
     * @param              $model_hydrate
     * @param              $result
     * @param              $model
     * @param bool         $is_select_columns_model
     *
     * @return AbstractPaginator|EloquentCollection|Model
     */
    private function processResultToHydrate(
        QueryBuilder $query,
        $args,
        $model_hydrate,
        $result,
        $model,
        bool $is_select_columns_model
    ) {
        $items = $result;
        if ($result instanceof AbstractPaginator) {
            $items = $result->getCollection();
        }

        $hydrated_models = $this->getHydratedModels($model_hydrate, $items, $model, $is_select_columns_model);
        $relationships   = $this->getModelsRelationships($query, $args, $model_hydrate, $items, $hydrated_models);

        if ($relationships->count() > 0) {
            $hydrated_models = $this->addRelationshipsToModel($model_hydrate, $model, $relationships, $hydrated_models);
        }

        if ($result instanceof AbstractPaginator) {
            return $result->setCollection($hydrated_models);
        } elseif ($result instanceof Collection) {
            return $hydrated_models;
        }

        return $hydrated_models->first();
    }

    /**
     * Convert query builder results in hydrated models
     * Example:
     * Model: JetAccount
     * -------------------------------------------------------------------------------------------------------------
     * QueryBuilderResult {[ jet_account_id: 1, jet_account_name: 'test', app_user_id: 1 ]}
     * Hydrated Result: EloquentCollection([ JetAccount(id = 1, name = 'test') ])
     * -------------------------------------------------------------------------------------------------------------
     * QueryBuilderResult {[ id: 1, name: 'test' ], [ id: 2, name: 'test 2' ]}
     * Hydrated Result: EloquentCollection([ JetAccount(id = 1, name = 'test'), JetAccount(id = 2, name = 'test 2') ])
     * -------------------------------------------------------------------------------------------------------------
     *
     * @param      $model_hydrate
     * @param      $result
     * @param      $model
     * @param bool $is_select_columns_model
     *
     * @return EloquentCollection
     */
    private function getHydratedModels(
        $model_hydrate,
        $result,
        $model,
        bool $is_select_columns_model
    ) {
        $hydrate_columns = collect($result instanceof Collection ? $result->toArray() : [(array) $result]);
        if (!$is_select_columns_model) {
            $search = $model_hydrate ?? $this->baseModel();
            if (is_null($search) || !array_key_exists($search, $this->models_aliases)) {
                $search = $this->table($search);
            }
            $search          .= '_';
            $hydrate_columns = $hydrate_columns
                ->transform(function ($columns) use ($search) {
                    $columns = (array) $columns;
                    foreach ($columns as $column => $value) {
                        if (!Str::startsWith($column, $search)) {
                            unset($columns[$column]);
                        }
                    }

                    return collect($columns)->mapWithKeys(function ($value, $column) use ($search) {
                        return [str_replace_first($search, '', $column) => $value];
                    })->toArray();
                })->filter(function ($columns) use ($model_hydrate) {
                    // When all the columns are null it means that there is no result
                    return !empty(array_filter($columns));
                });
        }

        return $model::hydrate($hydrate_columns->toArray());
    }

    /**
     * Get with_first and with from joined relationships and return array with all relationship results
     * Example:
     * Model JetAccount
     * Join: AppsUsers as apps_users
     * With: apps_users
     * -------------------------------------------------------------------------------------------------------------
     * QueryBuilderResult {[ id: 1, name: 'test' ]}
     * ResultArray: [ 'apps_users' => EloquentCollection([ AppUser(id = 1, name = 'app user test'), ... ]) ]
     * -------------------------------------------------------------------------------------------------------------
     *
     * @param QueryBuilder $query
     * @param              $args
     * @param              $model_hydrate
     * @param              $result
     * @param              $hydrated_models
     *
     * @return mixed
     */
    private function getModelsRelationships(QueryBuilder $query, $args, $model_hydrate, $result, $hydrated_models)
    {
        if (empty($this->with) && empty($this->with_first)) {
            return collect();
        }

        return collect($this->joins)->filter(function ($join) use ($model_hydrate) {
            return Str::startsWith($join, $model_hydrate . '->');
        })->mapWithKeys(function ($join) use ($query, $args, $hydrated_models, $result, $model_hydrate) {
            $join = last(explode('->', $join));

            if (Arr::has($this->with_first, $join)) {
                $model = Arr::has($this->models_aliases, $join) ?
                    with(new $this->models_aliases[$join]) :
                    with(new $join);

                $relationship_results = $this->processResultToHydrate($query, $args, $join, $result, $model, false);
                if (!is_null($relationship_results) && !$relationship_results instanceof EloquentCollection) {
                    $relationship_results = new EloquentCollection([$relationship_results]);
                }

                return [
                    $join . '.' . $this->with_first[$join] => $relationship_results
                ];
            } elseif (Arr::has($this->with, $join)) {
                $query->limit       = null;
                $query->unionLimit  = null;
                $query->offset      = null;
                $query->unionOffset = null;

                return [$join . '.' . $this->with[$join] => $this->callHydrateGetter($query, 'get', $args, $join)];
            }

            return [null];

        })->filter();
    }

    /**
     * Attach relationships from getModelsRelationships method to base models
     * Example:
     * Hydrated models: EloquentCollection([ JetAccount(id = 1, name = 'test') ])
     * With: apps_users
     * Relationships result: [ 'apps_users' => EloquentCollection([ AppUser(id = 1, name = 'app user test'), ... ]) ]
     * -------------------------------------------------------------------------------------------------------------
     * result: EloquentCollection([
     *  JetAccount {
     *      'attributes: {
     *          'id': 1,
     *          'name': 'test,
     *      },
     *      'relationships: [
     *          'apps_users': EloquentCollection([ AppUser(id = 1, name = 'app user test') ])
     *      ]
     *  }
     * ])
     * -------------------------------------------------------------------------------------------------------------
     *
     * @param      $model_hydrate
     * @param      $model
     * @param      $relationships
     * @param      $hydrated_models
     *
     * @return EloquentCollection|mixed
     */
    private function addRelationshipsToModel(
        $model_hydrate,
        $model,
        $relationships,
        $hydrated_models
    ) {
        if ($relationships->count() == 0) {
            return $hydrated_models;
        }

        $hydrated_models = $hydrated_models->all();
        $relationships->each(function ($relationship, $relation_key) use ($model, $model_hydrate, &$hydrated_models) {
            list($join_name, $join_relation) = explode('.', $relation_key);
            $relation      = $model->{$join_relation}();
            $relation_name = class_exists($join_name) ?
                str_replace('\\', '', $join_name) :
                $join_name;

            $hydrated_models = $relation->match(
                $relation->initRelation($hydrated_models, $relation_name),
                $relationship, $relation_name
            );
        });

        return new EloquentCollection($hydrated_models);
    }

    /**
     * @param QueryBuilder $query
     * @param string|null  $model
     *
     * @return $this
     */
    private function filterByNotDeleted(QueryBuilder $query, string $model = null)
    {
        $model       = $model ?? $this->baseModel();
        $model_class = Arr::get($this->models_aliases, $model, $model);

        if (in_array(SoftDeletes::class, class_uses($model_class))) {
            $query->whereNull($this->column('deleted_at', $model));
        }

        return $this;
    }
}
