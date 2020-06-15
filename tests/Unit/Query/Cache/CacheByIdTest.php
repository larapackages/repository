<?php

namespace Larapackages\Tests\Unit\Query\Cache;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Facades\Cache;
use Larapackages\Repository\Query\Cache\CacheById;
use Larapackages\Tests\Models\User;
use Larapackages\Tests\Repositories\Query\UserRepository;
use Larapackages\Tests\TestCase;
use stdClass;

/**
 * Class CacheByIdTest
 *
 * @package Larapackages\Tests\Unit\Query\Cache
 */
class CacheByIdTest extends TestCase
{
    public function testGetCacheKey()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60);

        $cache_key = $cache_by_result->getCacheKey('all', []);

        $expected_cache_key = sprintf(
            'id:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getQuery()->toSql(),
            json_encode($repository->getQuery()->getBindings())
        );
        $expected_cache_key = md5($expected_cache_key);

        $this->assertSame($expected_cache_key, $cache_key);
    }

    public function testGetWithoutCache()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60, ['get-without-cache']);

        $result = $cache_by_result->get($repository->getQuery(), 'all', [], function () {
            return 1;
        });
        $this->assertSame(1, $result);

        $cache_key = sprintf(
            'id:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getQuery()->toSql(),
            json_encode($repository->getQuery()->getBindings())
        );
        $cache_key = md5($cache_key);

        $this->assertSame(1, Cache::tags(['get-without-cache'])->get($cache_key));
    }

    public function testGetPksEmpty()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60, ['get-pks-empty']);

        $cache_key = sprintf(
            'id:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getQuery()->toSql(),
            json_encode($repository->getQuery()->getBindings())
        );
        $cache_key = md5($cache_key);

        Cache::tags(['get-pks-empty'])->put($cache_key, 'pks:', 60);

        $result = $cache_by_result->get($repository->getQuery(), 'all', [], function () {
            return 1;
        });
        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertEmpty($result);
    }

    public function testGetNullString()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60, ['get-null-string']);

        $cache_key = sprintf(
            'id:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getQuery()->toSql(),
            json_encode($repository->getQuery()->getBindings())
        );
        $cache_key = md5($cache_key);

        Cache::tags(['get-null-string'])->put($cache_key, 'null', 60);

        $result = $cache_by_result->get($repository->getQuery(), 'all', [], function () {
            return 1;
        });
        $this->assertNull($result);
    }

    public function testGetPksIds()
    {
        $user_1 = factory(User::class)->create();
        $user_2 = factory(User::class)->create();

        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60, ['get-pks-ids']);

        $cache_key = sprintf(
            'id:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getQuery()->toSql(),
            json_encode($repository->getQuery()->getBindings())
        );
        $cache_key = md5($cache_key);

        Cache::tags(['get-pks-ids'])->put(
            $cache_key,
            sprintf('pks:%d,%d', $user_1->id, $user_2->id),
            60
        );
        $result = $cache_by_result->get($repository->getQuery(), 'all', [], function () use ($repository, $user_1, $user_2) {
            $this->assertEquals([
                $user_1->id,
                $user_2->id
            ], $repository->getQuery()->wheres[0]['values']);
            $this->assertEquals([
                $user_1->id,
                $user_2->id
            ], $repository->getQuery()->getBindings());

            return $repository->get();
        });
        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertNotEmpty($result);
    }

    public function testWriteNull()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60, ['write-null']);

        $cache_by_result->write('all', [], null);

        $cache_key = sprintf(
            'id:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getQuery()->toSql(),
            json_encode($repository->getQuery()->getBindings())
        );
        $cache_key = md5($cache_key);
        $this->assertSame('null', Cache::tags(['write-null'])->get($cache_key));
    }

    public function testWriteObject()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60, ['write-object']);

        $class = new stdClass();
        $cache_by_result->write('all', [], $class);

        $cache_key = sprintf(
            'id:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getQuery()->toSql(),
            json_encode($repository->getQuery()->getBindings())
        );
        $cache_key = md5($cache_key);
        $this->assertSame($class, Cache::tags(['write-object'])->get($cache_key));
    }

    public function testWriteCollection()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60, ['write-collection']);

        $class = new EloquentCollection([
            $user_1 = factory(User::class)->create(),
            $user_2 = factory(User::class)->create(),
        ]);
        $cache_by_result->write('all', [], $class);

        $cache_key = sprintf(
            'id:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getQuery()->toSql(),
            json_encode($repository->getQuery()->getBindings())
        );
        $cache_key = md5($cache_key);
        $this->assertSame(
            sprintf('pks:%d,%d', $user_1->id, $user_2->id),
            Cache::tags(['write-collection'])->get($cache_key)
        );
    }

    public function testWriteAbstractPaginator()
    {
        $user_1 = factory(User::class)->create();
        $user_2 = factory(User::class)->create();

        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60, ['write-abstract-paginator']);

        $class = new class($user_1, $user_2) extends AbstractPaginator {
            public function __construct($user_1, $user_2)
            {
                $this->items = EloquentCollection::make([$user_1, $user_2]);
            }
        };
        $cache_by_result->write('all', [], $class);

        $cache_key = sprintf(
            'id:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getQuery()->toSql(),
            json_encode($repository->getQuery()->getBindings())
        );
        $cache_key = md5($cache_key);
        $this->assertSame(
            sprintf('pks:%d,%d', $user_1->id, $user_2->id),
            Cache::tags(['write-abstract-paginator'])->get($cache_key)
        );
    }

    public function testWriteRepositoryModel()
    {
        $user = factory(User::class)->create();

        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60, ['write-repository-model']);

        $cache_by_result->write('first', [], $user);

        $cache_key = sprintf(
            'id:%s($s):%s_%s',
            'first',
            json_encode([]),
            $repository->getQuery()->toSql(),
            json_encode($repository->getQuery()->getBindings())
        );
        $cache_key = md5($cache_key);
        $this->assertSame(
            sprintf('pks:%d', $user->id),
            Cache::tags(['write-repository-model'])->get($cache_key)
        );
    }

    public function testFlush()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheById($repository, 60, ['flush']);

        Cache::tags(['flush'])->put('testflush', 1, 60);

        $this->assertTrue(Cache::tags(['flush'])->has('testflush'));
        $cache_by_result->flush();
        $this->assertFalse(Cache::tags(['flush'])->has('testflush'));
    }
}