<?php

return [
    'lock_prefix' => 'lock',
    'lock_expire' => 60, // second

    'max_response_time'       => 10, // second timeout http
    'tolerance_response_time' => 100, // millisecond timout equal max_response_time * 1000 - tolerance

    /*
     * modes:
     * progressive: if timout 10 second and times 5, retry 600 ms, 1200 ms, 2400 ms, ...
     * diffused: if timout 10 second and times 5, retry approximately every 2 second
     * aggressive: for times 5 and sleep 100ms retry every 100 ms and then exit
     */
    'retry_mode'              => Celysium\Cache\Cache::PROGRESSIVE,
    'retry_sleep'             => 100, // millisecond use in aggressive mode delay every retry
    'retry_times'             => 5,   // retry fetch cache
];
