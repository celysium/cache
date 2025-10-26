<?php

namespace Celysium\Cache;


use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    const CONFIG_PATH = __DIR__ . '/../config/cache-manager.php';

    public function boot()
    {
        $this->publishes([
            self::CONFIG_PATH => base_path('config/cache-manager.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'cache-manager'
        );
    }
}
