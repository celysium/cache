<?php

namespace Celysium\Cache;

use Exception;
use Throwable;

class Cache
{
    const DIFFUSED = 'diffused';
    const PROGRESSIVE = 'progressive';
    const AGGRESSIVE = 'aggressive';

    private mixed $redis;
    private object $config;

    private array $delays = [];

    private int $sleep;

    private int $times;

    private int $timeout;

    private int $mode;

    private bool $associative = true;

    private bool $force = false;

    public function __construct()
    {
        $this->redis = app('redis');
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        $this->config = (object)config('cache-manager');

        $this->retry($this->config->retries->times, $this->config->retries->sleep);
        $this->timeout($this->config->retries->max_response_time);
        $this->mode($this->config->retries->mode);
    }

    /**
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     * @throws Throwable
     */
    public function resolve(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->redis->get($key);

        if ($value && !$this->force) {
            return json_decode($value, $this->associative);
        }

        $lockKey = sprintf("%s_%s", $this->config->lock_prefix, $key);

        if ($this->redis->set($lockKey, true, 'ex', $this->config->lock_expire, 'nx')) {
            try {
                $this->redis->set($key, json_encode($callback()), 'ex', $ttl);

                $value = $this->redis->get($key);
                $this->redis->del($lockKey);
                return json_decode($value, $this->associative);
            } catch (Exception $e) {
                $this->redis->del($lockKey);
                throw $e;
            }
        }

        $time = 0;
        do {
            $delay = $this->getDelay($time);
            if($delay == null) {
                break;
            }
            usleep($delay);

            if ($value = $this->redis->get($key)) {
                $this->redis->del($lockKey);
                return json_decode($value, $this->associative);
            }

            $time++;
            $remain = $this->redis->ttl($lockKey);
        } while ($remain > 0);

        if ($value = $this->redis->get($key)) {
            $this->redis->del($lockKey);
            return json_decode($value, $this->associative);
        }
        $this->redis->del($lockKey);
        return null;
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function associative(bool $value = true): self
    {
        $this->associative = $value;
        return $this;
    }


    /**
     * @param bool $value
     * @return $this
     */
    public function force(bool $value = false): self
    {
        $this->force = $value;
        return $this;
    }

    /**
     * @param int $time
     * @param int|null $sleep
     * @return $this
     */
    public function retry(int $time, int $sleep = null): self
    {
        $this->times = $time;
        if($sleep) {
            $this->sleep = $sleep;
        }
        return $this;
    }

    /**
     * @param int $second
     * @return $this
     */
    public function timeout(int $second): self
    {
        $this->setMaxTime($second);
        return $this;
    }

    /**
     * @param string $algorithm
     * @return $this
     */
    public function mode(string $algorithm): self
    {
        $this->mode = $algorithm;
        return $this;
    }

    /**
     * @param int $value
     * @return $this
     */
    public function sleep(int $value): self
    {
        $this->sleep = $value;
        return $this;
    }

    /**
     * @param int $timeout
     * @return void
     */
    private function setMaxTime(int $timeout): void
    {
        $this->timeout = ($timeout * 1000) - $this->config->retries->tolerance;
    }

    private function getDelay(int $time): int|null
    {
        $mode = $this->mode;

        return $this->$mode($time);
    }

    private function aggressive($time): int|null
    {
        return $time < $this->times ? $this->sleep : null;
    }

    private function diffused($time): int
    {
        return floor($this->timeout / $this->times) + ($time * 0);
    }

    private function progressive($time): int
    {
        if (isset($this->delays[$time])) {
            return $this->delays[$time];
        }

        $series = [];
        for ($index = 0; $index < $this->times; $index++) {
            $series[$index] = pow(2, $index);
        }

        $step = floor($this->timeout / array_sum($series));

        for ($index = 0; $index < $this->times; $index++) {
            $this->delays[] = $step * $series[$index];
        }

        return $this->delay[$time] ?? 0;
    }

    public function delete(string $key): int
    {
        return $this->redis->del($key);
    }
}
