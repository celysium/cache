<?php

namespace Celysium\Cache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed instance()
 * @method static int delete(string $key)
 * @method static mixed resolve(string $key, int $ttl, callable $callback)
 * @method static \Celysium\Cache\Cache associative(bool $value = true)
 * @method static \Celysium\Cache\Cache force(bool $value = false)
 * @method static \Celysium\Cache\Cache retry(int $time, int $sleep = null)
 * @method static \Celysium\Cache\Cache timeout(int $second)
 * @method static \Celysium\Cache\Cache mode(string $algorithm)
 * @method static \Celysium\Cache\Cache sleep(int $value)
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cache-manager';
    }
}