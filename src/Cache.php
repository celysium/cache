<?php

namespace Celysium\Cache;

use Exception;
use Illuminate\Redis\RedisManager;
use Throwable;

class Cache
{
    const DIFFUSED = 'diffused';
    const PROGRESSIVE = 'progressive';
    const AGGRESSIVE = 'aggressive';

    private RedisManager $redis;
    private null|object $config = null;

    private int $sleep;

    private int $times;

    private int $timeout;

    private string $mode;

    private string $lockKey = '';

    private bool $associative = true;

    private bool $fixedType = false;

    private bool $force = false;
    public function __construct()
    {
        $this->redis = app('redis');
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        $this->config = (object)config('cache-manager');

        $this->retry($this->config->retry_times, $this->config->retry_sleep);
        $this->timeout($this->config->max_response_time);
        $this->mode($this->config->retry_mode);
    }

    /**
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @param ?callable $onFill
     * @return mixed
     * @throws Throwable
     */
    public function resolve(string $key, int $ttl, callable $callback, callable $onFill = null): mixed
    {
        if (!$this->force && $this->redis->exists($key)) {
            return $this->getKey($key);
        }

        $this->lockKey = $this->lockKey ?: sprintf("%s_%s", $this->config->lock_prefix, $key);

        if ($this->redis->set($this->lockKey, true, 'ex', $this->config->lock_expire, 'nx')) {
            try {
                $result = $callback();
                $this->redis->set($key, ($this->fixedType ? $result : json_encode($result)), 'ex', $ttl);

                $value = $this->getKey($key);
                if($onFill) {
                    $onFill($value);
                }
                $this->redis->del($this->lockKey);
                return $value;
            } catch (Exception $e) {
                $this->redis->del($this->lockKey);
                throw $e;
            }
        }

        $time = 0;
        do {
            usleep($this->getDelay($time));

            if ($this->redis->exists($key)) {
                return $this->getKey($key);
            }
            $time++;
        } while ($time < $this->times);

        if ($this->redis->exists($key)) {
            return $this->getKey($key);
        }
        return null;
    }

    private function getKey($key): mixed
    {
        $value = $this->redis->get($key);
        return $this->fixedType ? $value : (is_string($value) ? json_decode($value, $this->associative) : $value);
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
    public function fixedType(bool $value = true): self
    {
        $this->fixedType = $value;
        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function lockKey(string $name): self
    {
        $this->lockKey = $name;
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
        $this->timeout = ($timeout * 1000) - $this->config->tolerance_response_time;
    }

    private function getDelay(int $time): int
    {
        $mode = $this->mode;

        return $this->$mode($time);
    }

    private function aggressive($time): int
    {
        return $this->sleep + ($time * 0);
    }

    private function diffused($time): int
    {
        return floor($this->timeout / $this->times) + ($time * 0);
    }

    private function progressive($time): int
    {
        $step = floor($this->timeout / (pow(2, $this->times) - 1));

        return $step * pow(2, $time);
    }

    public function delete(string $key): int
    {
        return $this->redis->del($key);
    }

    public function set(string $key, int $ttl, mixed $value): bool
    {
        return $this->redis->set($key, $value, 'ex', $ttl);
    }

    public static function instance(): self
    {
        return new self();
    }
}
