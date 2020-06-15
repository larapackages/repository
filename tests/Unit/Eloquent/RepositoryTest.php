<?php

namespace Larapackages\Tests\Unit\Eloquent;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Larapackages\Repository\Eloquent\Cache\CacheById;
use Larapackages\Repository\Eloquent\Cache\CacheByResult;
use Larapackages\Repository\Eloquent\Cache\CacheStrategy;
use Larapackages\Repository\Eloquent\Exceptions\CacheStrategyNotFoundException;
use Larapackages\Repository\Eloquent\Exceptions\CanNotSpecifyOrderByClauseException;
use Larapackages\Repository\Eloquent\Exceptions\MethodNotFoundException;
use Larapackages\Repository\Eloquent\Exceptions\InvalidOperatorException;
use Larapackages\Tests\Models\Post;
use Larapackages\Tests\Models\User;
use Larapackages\Tests\Repositories\Eloquent\PostRepository;
use Larapackages\Tests\TestCase;
use Larapackages\Tests\Traits\ReflectionTrait;

/**
 * Class RepositoryTest
 *
 * @package Larapackages\Tests\Unit\Eloquent
 */
class RepositoryTest extends TestCase
{
    use ReflectionTrait;

    public function testConstruct()
    {
        $repository = new PostRepository();

        $this->assertInstanceOf($repository->model(), $this->getClassProperty($repository, 'model', 1));
    }

    public function testMakeModel()
    {
        $repository = new PostRepository();

        $this->setClassProperty($repository, 'model', null, 1);
        $this->assertNull($this->getClassProperty($repository, 'model', 1));

        $repository->makeModel();

        $this->assertInstanceOf($repository->model(), $this->getClassProperty($repository, 'model', 1));
    }

    public function testGetModel()
    {
        $repository = new PostRepository();

        $this->assertSame($repository->getModel(), $this->getClassProperty($repository, 'model', 1));
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

    public function testFilterByKeySingleValue()
    {
        $repository = new PostRepository();

        $repository->filterByKey(1);

        $this->assertEquals(
            [
                [
                    'type'     => 'Basic',
                    'column'   => 'id',
                    'operator' => '=',
                    'value'    => 1,
                    'boolean'  => 'and',
                ]
            ],
            $repository->getModel()->getQuery()->wheres
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
                    'column'   => 'id',
                    'operator' => '!=',
                    'value'    => 1,
                    'boolean'  => 'and',
                ]
            ],
            $repository->getModel()->getQuery()->wheres
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
                    'column'  => 'id',
                    'values'  => [1, 2],
                    'boolean' => 'and',
                ]
            ],
            $repository->getModel()->getQuery()->wheres
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
                    'column'  => 'id',
                    'values'  => [1, 2],
                    'boolean' => 'and',
                ]
            ],
            $repository->getModel()->getQuery()->wheres
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
                    'column'    => 'id',
                    'direction' => 'asc',
                ]
            ],
            $repository->getModel()->getQuery()->orders
        );
    }

    public function testOrderByKeyWithDirection()
    {
        $repository = new PostRepository();

        $repository->orderByKey('desc');

        $this->assertEquals(
            [
                [
                    'column'    => 'id',
                    'direction' => 'desc',
                ]
            ],
            $repository->getModel()->getQuery()->orders
        );
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

    public function testGetCache()
    {
        $repository = new PostRepository();

        $this->assertNull($repository->getCache());

        $cacheStrategy = new class implements CacheStrategy {
            public function get(string $method, array $args, callable $default)
            {
            }

            public function write(string $method, array $args, $result): void
            {
            }

            public function flush(): void
            {
            }
        };

        $this->setClassProperty($repository, 'eloquent_cache', $cacheStrategy, 1);
        $this->assertSame($cacheStrategy, $repository->getCache());
    }

    public function testWithCacheIdStrategy()
    {
        $repository = new PostRepository();
        $repository->withCache(60, [], 'id');

        $this->assertInstanceOf(
            CacheById::class,
            $this->getClassProperty($repository, 'eloquent_cache', 1)
        );
    }

    public function testWithCacheResultStrategy()
    {
        $repository = new PostRepository();
        $repository->withCache(60, [], 'result');

        $this->assertInstanceOf(
            CacheByResult::class,
            $this->getClassProperty($repository, 'eloquent_cache', 1)
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
            public function get(string $method, array $args, callable $default)
            {
            }

            public function write(string $method, array $args, $result): void
            {
            }

            public function flush(): void
            {
            }
        };
        $this->setClassProperty($repository, 'eloquent_cache', $cacheStrategy, 1);

        $this->assertSame($cacheStrategy, $this->getClassProperty($repository, 'eloquent_cache', 1));
        $repository->withoutCache();
        $this->assertNull($this->getClassProperty($repository, 'eloquent_cache', 1));
    }

    public function test__Clone()
    {
        $repository      = new PostRepository();
        $repositoryClone = clone $repository;

        $this->assertEquals(
            $this->getClassProperty($repository, 'model', 1),
            $this->getClassProperty($repositoryClone, 'model', 1)
        );

        $this->assertNotSame(
            $this->getClassProperty($repository, 'model', 1),
            $this->getClassProperty($repositoryClone, 'model', 1)
        );
    }

    public function test__CallMethodNotFound()
    {
        $repository = new PostRepository();

        $this->expectException(MethodNotFoundException::class);
        $repository->unknownMethod();
    }

    public function test__CallEloquentAvailable()
    {
        $repository = new PostRepository();

        $repository->whereNull('deleted_at');

        $this->assertEquals(
            [
                [
                    'type'     => 'Null',
                    'column'   => 'deleted_at',
                    'boolean'  => 'and',
                ]
            ],
            $repository->getModel()->getQuery()->wheres
        );
    }

    public function test__CallEloquentGetter()
    {
        $repository = new PostRepository();

        $this->assertCount(0, $repository->all());
    }

    public function test__CallEloquentGetterWithCache()
    {
        $repository = new PostRepository();

        $cacheStrategy = new class implements CacheStrategy {
            public function get(string $method, array $args, callable $default)
            {
                return 'cache';
            }

            public function write(string $method, array $args, $result): void
            {
            }

            public function flush(): void
            {
            }
        };
        $this->setClassProperty($repository, 'eloquent_cache', $cacheStrategy, 1);

        $this->assertSame('cache', $repository->all());
    }
}