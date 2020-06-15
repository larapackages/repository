<?php

namespace Larapackages\Tests\Unit\Query;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression as QueryExpression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Larapackages\Repository\Query\Cache\CacheById;
use Larapackages\Repository\Query\Cache\CacheByResult;
use Larapackages\Repository\Query\Cache\CacheStrategy;
use Larapackages\Repository\Query\Exceptions\CacheStrategyNotFoundException;
use Larapackages\Repository\Query\Exceptions\CanNotSpecifyOrderByClauseException;
use Larapackages\Repository\Query\Exceptions\InvalidOperatorException;
use Larapackages\Repository\Query\Exceptions\MethodNotFoundException;
use Larapackages\Tests\Models\Post;
use Larapackages\Tests\Models\User;
use Larapackages\Tests\Repositories\Query\PostRepository;
use Larapackages\Tests\Repositories\Query\UserRepository;
use Larapackages\Tests\TestCase;
use Larapackages\Tests\Traits\ReflectionTrait;

/**
 * Class RepositoryTest
 *
 * @package Larapackages\Tests\Unit\Query
 */
class RepositoryTest extends TestCase
{
    use ReflectionTrait;

    public function testConstruct()
    {
        $repository = new PostRepository();

        $this->assertInstanceOf(Post::class, $repository->base_model);
        $this->assertEmpty($this->getClassProperty($repository, 'with', 1));
        $this->assertEmpty($this->getClassProperty($repository, 'joins', 1));
        $this->assertEmpty($this->getClassProperty($repository, 'models_aliases', 1));
        $this->assertNull($this->getClassProperty($repository, 'hydrate', 1));
        $this->assertEquals(
            [Post::class => with(new Post)->getTable()],
            $this->getClassProperty($repository, 'models_tables', 1)
        );
        $this->assertEquals(
            DB::connection(with(new Post)->getConnectionName())->table(with(new Post)->getTable()),
            $this->getClassProperty($repository, 'query', 1)
        );
        $this->assertNull($this->getClassProperty($repository, 'query_cache', 1));
    }

    public function testGetQuery()
    {
        $repository = new PostRepository();

        $this->assertEquals(
            DB::connection(with(new Post)->getConnectionName())->table(with(new Post)->getTable()),
            $repository->getQuery()
        );
    }

    public function testHydrate()
    {
        $repository = new PostRepository();

        $this->assertNull($this->getClassProperty($repository, 'hydrate', 1));

        $repository->hydrate(Post::class);

        $this->assertEquals(
            Post::class,
            $this->getClassProperty($repository, 'hydrate', 1)
        );
    }

    public function testWith()
    {
        $repository = new PostRepository();

        $this->setClassProperty($repository, 'with_first', ['user' => 'user'], 1);
        $this->assertEmpty($this->getClassProperty($repository, 'with', 1));

        $repository->with('user', 'user');

        $this->assertEquals(
            ['user' => 'user'],
            $this->getClassProperty($repository, 'with', 1)
        );
        $this->assertEmpty($this->getClassProperty($repository, 'with_first', 1));
    }

    public function testWithFirst()
    {
        $repository = new PostRepository();

        $this->setClassProperty($repository, 'with', ['user' => 'user'], 1);
        $this->assertEmpty($this->getClassProperty($repository, 'with_first', 1));
        $repository->withFirst('user', 'user');
        $this->assertEmpty($this->getClassProperty($repository, 'with_first', 1));

        $repository->withFirst('user_active', 'user');
        $this->assertEquals(
            ['user_active' => 'user'],
            $this->getClassProperty($repository, 'with_first', 1)
        );
    }

    public function testAddModelAlias()
    {
        $repository = new PostRepository();

        $repository->addModelAlias(User::class, 'user');

        $this->assertEquals(
            ['user' => User::class],
            $this->getClassProperty($repository, 'models_aliases', 1)
        );
    }

    public function testColumn()
    {
        $repository = new PostRepository();

        $this->assertEquals(
            with(new Post)->getTable() . '.id',
            $repository->column('id')
        );

        $this->setClassProperty($repository, 'models_aliases', ['user' => User::class], 1);

        $this->assertEquals(
            'user.id',
            $repository->column('id', 'user')
        );

        $this->assertEquals(
            with(new User)->getTable() . '.id',
            $repository->column('id', User::class)
        );
    }

    public function testRawColumn()
    {
        $repository = new PostRepository();

        $rawColumn = $repository->rawColumn('id');
        $this->assertInstanceOf(QueryExpression::class, $rawColumn);
        $this->assertEquals(
            with(new Post)->getTable() . '.id',
            $rawColumn
        );

        $this->setClassProperty($repository, 'models_aliases', ['user' => User::class], 1);

        $rawColumn = $repository->rawColumn('id', 'user');
        $this->assertInstanceOf(QueryExpression::class, $rawColumn);
        $this->assertEquals(
            'user.id',
            $rawColumn
        );

        $rawColumn = $repository->rawColumn('id', User::class);
        $this->assertInstanceOf(QueryExpression::class, $rawColumn);
        $this->assertEquals(
            with(new User)->getTable() . '.id',
            $rawColumn
        );
    }

    public function testTable()
    {
        $repository = new PostRepository();

        $this->assertEquals(
            with(new Post)->getTable(),
            $repository->table()
        );

        $this->assertEquals(
            with(new User)->getTable(),
            $repository->table(User::class)
        );

        $this->setClassProperty($repository, 'models_aliases', ['user' => User::class], 1);
        $this->assertEquals(
            with(new User)->getTable() . ' as user',
            $repository->table('user')
        );
    }

    public function testFilterByKeySingleValue()
    {
        $repository = new PostRepository();

        $repository->filterByKey(1);

        $this->assertEquals(
            [
                [
                    'type'     => 'Basic',
                    'column'   => with(new Post)->getTable() . '.id',
                    'operator' => '=',
                    'value'    => 1,
                    'boolean'  => 'and',
                ]
            ],
            $repository->getQuery()->wheres
        );
    }

    public function testFilterByKeySingleValueWithOperator()
    {
        $repository = new PostRepository();

        $repository->filterByKey(1, '!=');

        $this->assertEquals(
            [
                [
                    'type'     => 'Basic',
                    'column'   => with(new Post)->getTable() . '.id',
                    'operator' => '!=',
                    'value'    => 1,
                    'boolean'  => 'and',
                ]
            ],
            $repository->getQuery()->wheres
        );
    }

    public function testFilterByKeyArrayValue()
    {
        $repository = new PostRepository();

        $repository->filterByKey([1, 2]);

        $this->assertEquals(
            [
                [
                    'type'    => 'In',
                    'column'  => with(new Post)->getTable() . '.id',
                    'values'  => [1, 2],
                    'boolean' => 'and',
                ]
            ],
            $repository->getQuery()->wheres
        );
    }

    public function testFilterByKeyArrayValueNotIn()
    {
        $repository = new PostRepository();

        $repository->filterByKey([1, 2], '!=');

        $this->assertEquals(
            [
                [
                    'type'    => 'NotIn',
                    'column'  => with(new Post)->getTable() . '.id',
                    'values'  => [1, 2],
                    'boolean' => 'and',
                ]
            ],
            $repository->getQuery()->wheres
        );
    }

    public function testFilterByKeyArrayInvalidOperator()
    {
        $repository = new PostRepository();

        $this->expectException(InvalidOperatorException::class);
        $repository->filterByKey([1, 2], '>');
    }

    public function testOrderByKey()
    {
        $repository = new PostRepository();

        $repository->orderByKey();

        $this->assertEquals(
            [
                [
                    'column'    => with(new Post)->getTable() . '.id',
                    'direction' => 'asc',
                ]
            ],
            $repository->getQuery()->orders
        );
    }

    public function testOrderByKeyWithDirection()
    {
        $repository = new PostRepository();

        $repository->orderByKey('desc');

        $this->assertEquals(
            [
                [
                    'column'    => with(new Post)->getTable() . '.id',
                    'direction' => 'desc',
                ]
            ],
            $repository->getQuery()->orders
        );
    }

    public function testWithCacheIdStrategy()
    {
        $repository = new PostRepository();
        $repository->withCache(60, [], 'id');

        $this->assertInstanceOf(
            CacheById::class,
            $this->getClassProperty($repository, 'query_cache', 1)
        );
    }

    public function testWithCacheResultStrategy()
    {
        $repository = new PostRepository();
        $repository->withCache(60, [], 'result');

        $this->assertInstanceOf(
            CacheByResult::class,
            $this->getClassProperty($repository, 'query_cache', 1)
        );
    }

    public function testWithCacheUnknownStrategy()
    {
        $repository = new PostRepository();
        $this->expectException(CacheStrategyNotFoundException::class);
        $repository->withCache(60, [], 'unknown');
    }

    public function testWithoutCache()
    {
        $repository = new PostRepository();

        $cacheStrategy = new class implements CacheStrategy {
            public function get(QueryBuilder $query, string $method, array $args, callable $default)
            {
            }

            public function write(string $method, array $args, $result): void
            {
            }

            public function flush(): void
            {
            }
        };
        $this->setClassProperty($repository, 'query_cache', $cacheStrategy, 1);

        $this->assertSame($cacheStrategy, $this->getClassProperty($repository, 'query_cache', 1));
        $repository->withoutCache();
        $this->assertNull($this->getClassProperty($repository, 'query_cache', 1));
    }

    public function testCount()
    {
        $factoryUser  = factory(User::class)->create();
        $factoryPosts = factory(Post::class, 3)->create(['user_id' => $factoryUser->id]);
        $repository   = new PostRepository();

        $this->assertSame(3, $repository->count());
        $factoryPosts->first()->delete();
        $this->assertSame(2, $repository->count());
    }

    public function testDoWhile()
    {
        $factoryUser  = factory(User::class)->create();
        factory(Post::class, 10)->create([
            'user_id' => $factoryUser->id,
            'title'   => 'Title fake',
        ]);
        $repository   = new PostRepository();

        $query_iterations = 0;
        $posts_iterations = 0;
        $repository->filterByTitle('Title fake')
            ->doWhile(5, function ($posts) use (&$query_iterations, &$posts_iterations) {
                $query_iterations++;
                $posts->each(function (Post $post) use (&$posts_iterations) {
                    $posts_iterations++;
                    $post->title = 'Another title';
                    $post->save();
                });
            });

        $this->assertSame(2, $query_iterations);
        $this->assertSame(10, $posts_iterations);
    }

    public function testDoWhileReturnFalse()
    {
        $factoryUser  = factory(User::class)->create();
        factory(Post::class, 5)->create(['user_id' => $factoryUser->id]);
        $repository   = new PostRepository();

        $iterations = 0;
        $repository->doWhile(2, function (EloquentCollection $posts) use (&$iterations) {
            $iterations++;
            return false;
        });

        $this->assertTrue($iterations === 1);
    }

    public function testChunk()
    {
        $factoryUser  = factory(User::class)->create();
        $factoryPosts = factory(Post::class, 6)->create(['user_id' => $factoryUser->id]);
        $repository   = new PostRepository();

        $iterations = 0;
        $repository->chunk(2, function (EloquentCollection $posts) use (&$iterations, $factoryPosts) {
            $iterations++;
            $posts->each(function (Post $post) use ($factoryPosts) {
                $this->assertInstanceOf(Post::class, $factoryPosts->where('id', $post->id)->first());
            });
        });

        $this->assertTrue($iterations === 3);
    }

    public function testChunkReturnFalse()
    {
        $factoryUser  = factory(User::class)->create();
        factory(Post::class, 5)->create(['user_id' => $factoryUser->id]);
        $repository   = new PostRepository();

        $iterations = 0;
        $repository->chunk(2, function (EloquentCollection $posts) use (&$iterations) {
            $iterations++;
            return false;
        });

        $this->assertTrue($iterations === 1);

        $iterations = 0;
        $repository->chunk(2, function (EloquentCollection $posts) use (&$iterations) {
            $iterations++;
            return true;
        });

        $this->assertTrue($iterations === 3);
    }

    public function testChunkByIdWithPreviousOrder()
    {
        $repository   = new PostRepository();

        $this->expectException(CanNotSpecifyOrderByClauseException::class);
        $repository->orderByTitle()->chunkById(1, function() {});
    }

    public function testChunkById()
    {
        $factoryUser  = factory(User::class)->create();
        $factoryPosts = factory(Post::class, 5)->create(['user_id' => $factoryUser->id]);
        $repository   = new PostRepository();

        $iterations = 0;
        $repository->chunkById(2, function (EloquentCollection $posts) use (&$iterations, $factoryPosts) {
            $iterations++;
            $posts->each(function (Post $post) use ($factoryPosts) {
                $this->assertInstanceOf(Post::class, $factoryPosts->where('id', $post->id)->first());
            });
        });

        $this->assertTrue($iterations === 3);
    }

    public function testChunkByIdReturnFalse()
    {
        $factoryUser  = factory(User::class)->create();
        factory(Post::class, 5)->create(['user_id' => $factoryUser->id]);
        $repository   = new PostRepository();

        $iterations = 0;
        $repository->chunkById(2, function (EloquentCollection $posts) use (&$iterations) {
            $iterations++;
            return false;
        });

        $this->assertTrue($iterations === 1);
    }

    public function testFirstOrFail()
    {
        $factoryUser  = factory(User::class)->create();
        $repository   = new UserRepository();

        $this->assertEquals(
            User::find($factoryUser->id),
            $repository->firstOrFail()
        );

        $factoryUser->delete();
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('No query results for model ['.User::class.'].');
        $repository->firstOrFail();
    }

    public function test__Clone()
    {
        $repository      = new PostRepository();
        $repositoryClone = clone $repository;

        $this->assertEquals(
            $this->getClassProperty($repository, 'query', 1),
            $this->getClassProperty($repositoryClone, 'query', 1)
        );

        $this->assertNotSame(
            $this->getClassProperty($repository, 'query', 1),
            $this->getClassProperty($repositoryClone, 'query', 1)
        );
    }

    public function test__CallMethodNotFound()
    {
        $repository = new PostRepository();

        $this->expectException(MethodNotFoundException::class);
        $repository->unknownMethod();
    }

    public function test__CallQueryHydrateGetter()
    {
        $repository = new PostRepository();

        $this->assertCount(0, $repository->get());
    }

    public function test__CallQueryGetter()
    {
        $repository = new PostRepository();

        $this->assertNull($repository->max('id'));
    }

    public function testWhen()
    {
        $post_repository = new PostRepository();

        $post_repository->when('property-value', function ($repository, $value) use ($post_repository) {
            $this->assertSame($post_repository, $repository);
            $this->assertSame('property-value', $value);
        }, function () {
            $this->assertTrue(false, 'Must not be executed');
        });

        $post_repository->when(false, function () {
            $this->assertTrue(false, 'Must not be executed');
        }, function ($repository, $value) use ($post_repository) {
            $this->assertSame($post_repository, $repository);
            $this->assertFalse($value);
        });

        $post_repository->when(false, function () {
            $this->assertTrue(false, 'Must not be executed');
        });
    }

    public function testJoinSimple()
    {
        $post_repository = new PostRepository();

        $post_repository->join(User::class, 'user_id', 'id');
        $this->assertCount(1, $post_repository->getQuery()->joins);

        /** @var JoinClause $join */
        $join = $post_repository->getQuery()->joins[0];
        $this->assertNull($join->type);
        $this->assertSame(with(new User)->getTable(), $join->table);
        $this->assertEquals(
            [
                [
                    'type'     => 'Column',
                    'first'   => with(new Post)->getTable() . '.user_id',
                    'operator' => '=',
                    'second'    => with(new User())->getTable() . '.id',
                    'boolean'  => 'and',
                ],
                [
                    'type'     => 'Null',
                    'column'   => with(new User)->getTable() . '.deleted_at',
                    'boolean'  => 'and',
                ],
            ],
            $join->wheres
        );
    }

    public function testJoinComplex()
    {
        $post_repository = new PostRepository();

        $post_repository->join(User::class, function (JoinClause $join) use ($post_repository) {
            $join->on($post_repository->column('user_id'), '=', $post_repository->column('id', User::class))
                ->where($post_repository->column('created_at', User::class), '>', '2017-01-01');
        });
        $this->assertCount(1, $post_repository->getQuery()->joins);

        /** @var JoinClause $join */
        $join = $post_repository->getQuery()->joins[0];
        $this->assertNull($join->type);
        $this->assertSame(with(new User)->getTable(), $join->table);
        $this->assertEquals(
            [
                [
                    'type'     => 'Column',
                    'first'   => with(new Post)->getTable() . '.user_id',
                    'operator' => '=',
                    'second'    => with(new User)->getTable() . '.id',
                    'boolean'  => 'and',
                ],
                [
                    'type'     => 'Basic',
                    'column'   => with(new User)->getTable() . '.created_at',
                    'operator' => '>',
                    'value'    => '2017-01-01',
                    'boolean'  => 'and',
                ],
                [
                    'type'     => 'Null',
                    'column'   => with(new User)->getTable() . '.deleted_at',
                    'boolean'  => 'and',
                ],
            ],
            $join->wheres
        );
    }

    public function testLeftJoinSimple()
    {
        $post_repository = new PostRepository();

        $post_repository->leftJoin(User::class, 'user_id', 'id');
        $this->assertCount(1, $post_repository->getQuery()->joins);

        /** @var JoinClause $join */
        $join = $post_repository->getQuery()->joins[0];
        $this->assertSame('left', $join->type);
        $this->assertSame(with(new User)->getTable(), $join->table);
        $this->assertEquals(
            [
                [
                    'type'     => 'Column',
                    'first'   => with(new Post)->getTable() . '.user_id',
                    'operator' => '=',
                    'second'    => with(new User())->getTable() . '.id',
                    'boolean'  => 'and',
                ],
                [
                    'type'     => 'Null',
                    'column'   => with(new User)->getTable() . '.deleted_at',
                    'boolean'  => 'and',
                ],
            ],
            $join->wheres
        );
    }

    public function testLeftJoinComplex()
    {
        $post_repository = new PostRepository();

        $post_repository->leftJoin(User::class, function (JoinClause $join) use ($post_repository) {
            $join->on($post_repository->column('user_id'), '=', $post_repository->column('id', User::class))
                ->where($post_repository->column('created_at', User::class), '>', '2017-01-01');
        });
        $this->assertCount(1, $post_repository->getQuery()->joins);

        /** @var JoinClause $join */
        $join = $post_repository->getQuery()->joins[0];
        $this->assertSame('left', $join->type);
        $this->assertSame(with(new User)->getTable(), $join->table);
        $this->assertEquals(
            [
                [
                    'type'     => 'Column',
                    'first'   => with(new Post)->getTable() . '.user_id',
                    'operator' => '=',
                    'second'    => with(new User)->getTable() . '.id',
                    'boolean'  => 'and',
                ],
                [
                    'type'     => 'Basic',
                    'column'   => with(new User)->getTable() . '.created_at',
                    'operator' => '>',
                    'value'    => '2017-01-01',
                    'boolean'  => 'and',
                ],
                [
                    'type'     => 'Null',
                    'column'   => with(new User)->getTable() . '.deleted_at',
                    'boolean'  => 'and',
                ],
            ],
            $join->wheres
        );
    }

    public function testRightJoinSimple()
    {
        $post_repository = new PostRepository();

        $post_repository->rightJoin(User::class, 'user_id', 'id');
        $this->assertCount(1, $post_repository->getQuery()->joins);

        /** @var JoinClause $join */
        $join = $post_repository->getQuery()->joins[0];
        $this->assertSame('right', $join->type);
        $this->assertSame(with(new User)->getTable(), $join->table);
        $this->assertEquals(
            [
                [
                    'type'     => 'Column',
                    'first'   => with(new Post)->getTable() . '.user_id',
                    'operator' => '=',
                    'second'    => with(new User())->getTable() . '.id',
                    'boolean'  => 'and',
                ],
                [
                    'type'     => 'Null',
                    'column'   => with(new User)->getTable() . '.deleted_at',
                    'boolean'  => 'and',
                ],
            ],
            $join->wheres
        );
    }

    public function testRightJoinComplex()
    {
        $post_repository = new PostRepository();

        $post_repository->rightJoin(User::class, function (JoinClause $join) use ($post_repository) {
            $join->on($post_repository->column('user_id'), '=', $post_repository->column('id', User::class))
                ->where($post_repository->column('created_at', User::class), '>', '2017-01-01');
        });
        $this->assertCount(1, $post_repository->getQuery()->joins);

        /** @var JoinClause $join */
        $join = $post_repository->getQuery()->joins[0];
        $this->assertSame('right', $join->type);
        $this->assertSame(with(new User)->getTable(), $join->table);
        $this->assertEquals(
            [
                [
                    'type'     => 'Column',
                    'first'   => with(new Post)->getTable() . '.user_id',
                    'operator' => '=',
                    'second'    => with(new User)->getTable() . '.id',
                    'boolean'  => 'and',
                ],
                [
                    'type'     => 'Basic',
                    'column'   => with(new User)->getTable() . '.created_at',
                    'operator' => '>',
                    'value'    => '2017-01-01',
                    'boolean'  => 'and',
                ],
                [
                    'type'     => 'Null',
                    'column'   => with(new User)->getTable() . '.deleted_at',
                    'boolean'  => 'and',
                ],
            ],
            $join->wheres
        );
    }

    public function testInit()
    {
        //@todo
    }

    public function testCallGetter()
    {
        //@todo
    }

    public function testCallHydrateGetter()
    {
        //@todo
    }

    public function testGetColumnsFromInformationSchema()
    {
        //@todo
        //$post_repository = new PostRepository();
        //
        //$post_repository
        //    ->addModelAlias(User::class, 'user')
        //    ->join('user', 'user_id', 'id')
        //    ->with('user', 'user');
        //
        //dd($this->getClassMethod($post_repository, 'getColumnsFromInformationSchemaToSelect', 1));
    }
}