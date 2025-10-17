<?php

namespace Celysium\Cache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed resolve(string $key, int $ttl, callable $callback)
 * @method static mixed associative(bool $value = true)
 * @method static mixed force(bool $value = false)
 * @method static mixed retry(int $time, int $sleep = null)
 * @method static mixed timeout(int $second)
 * @method static mixed mode(string $algorithm)
 * @method static mixed sleep(int $value)
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cache-manager';
    }
}