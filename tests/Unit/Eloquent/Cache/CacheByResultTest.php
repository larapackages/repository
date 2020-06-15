<?php

namespace Larapackages\Tests\Unit\Eloquent\Cache;

use Illuminate\Support\Facades\Cache;
use Larapackages\Repository\Eloquent\Cache\CacheByResult;
use Larapackages\Tests\Repositories\Eloquent\UserRepository;
use Larapackages\Tests\TestCase;

/**
 * Class CacheByResultTest
 *
 * @package Larapackages\Tests\Unit\Eloquent\Cache
 */
class CacheByResultTest extends TestCase
{
    public function testGetCacheKey()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheByResult($repository, 60);

        $cache_key = $cache_by_result->getCacheKey('all', []);

        $expected_cache_key = sprintf(
            'result:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getModel()->toSql(),
            json_encode($repository->getModel()->getBindings())
        );
        $expected_cache_key = md5($expected_cache_key);

        $this->assertSame($expected_cache_key, $cache_key);
    }

    public function testGet()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheByResult($repository, 60, ['get']);

        $result = $cache_by_result->get('all', [], function () {
            return 1;
        });
        $this->assertSame(1, $result);

        $result = $cache_by_result->get('all', [], function () {
            return 2;
        });
        $this->assertSame(1, $result);
    }

    public function testWrite()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheByResult($repository, 60, ['write']);

        $cache_by_result->write('all', [], 1);

        $cache_key = sprintf(
            'result:%s($s):%s_%s',
            'all',
            json_encode([]),
            $repository->getModel()->toSql(),
            json_encode($repository->getModel()->getBindings())
        );
        $cache_key = md5($cache_key);

        $this->assertSame(1, Cache::tags(['write'])->get($cache_key));
    }

    public function testFlush()
    {
        $repository      = new UserRepository;
        $cache_by_result = new CacheByResult($repository, 60, ['flush']);

        Cache::tags(['flush'])->put('testflush', 1, 60);

        $this->assertTrue(Cache::tags(['flush'])->has('testflush'));
        $cache_by_result->flush();
        $this->assertFalse(Cache::tags(['flush'])->has('testflush'));
    }
}