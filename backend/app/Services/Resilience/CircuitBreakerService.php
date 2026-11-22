<?php

namespace App\Services\Resilience;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Week 16 — Module 16 / SA10: circuit breaker for automation dispatcher.
 *
 * Three-state circuit breaker (CLOSED, OPEN, HALF_OPEN) backed by Laravel Cache.
 * Protects the automation dispatcher from cascading failures by failing fast
 * when the downstream is consistently failing.
 */
class CircuitBreakerService
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    public const STATES = [self::STATE_CLOSED, self::STATE_OPEN, self::STATE_HALF_OPEN];

    private const CACHE_KEY = 'circuit_breaker:automation_dispatcher';

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $openTtlSeconds = 60,
        private readonly int $halfOpenProbes = 3,
    ) {
    }

    /**
     * Execute a callback with circuit breaker protection.
     *
     * If the circuit is OPEN, throws immediately without calling the callback.
     * If the circuit is HALF_OPEN, allows a limited number of probe requests.
     * If the circuit is CLOSED, executes normally and tracks failures.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws \RuntimeException if circuit is OPEN
     */
    public function execute(callable $callback)
    {
        $state = $this->currentState();

        if ($state === self::STATE_OPEN) {
            throw new \RuntimeException('Circuit breaker is OPEN for automation dispatcher');
        }

        if ($state === self::STATE_HALF_OPEN) {
            $probe = $this->acquireHalfOpenProbe();

            if (! $probe) {
                throw new \RuntimeException('Circuit breaker is HALF_OPEN and probe limit reached');
            }
        }

        try {
            $result = $callback();
            $this->onSuccess();
            return $result;
        } catch (Throwable $e) {
            $this->onFailure();
            throw $e;
        }
    }

    /**
     * Get the current circuit breaker state without side effects.
     */
    public function currentState(): string
    {
        $state = Cache::get(self::CACHE_KEY.':state', self::STATE_CLOSED);

        if ($state === self::STATE_OPEN) {
            $openedAt = Cache::get(self::CACHE_KEY.':opened_at', 0);
            $openTtl = $this->openTtlSeconds;

            if (now()->timestamp - $openedAt >= $openTtl) {
                $this->transitionToHalfOpen();
                return self::STATE_HALF_OPEN;
            }
        }

        return $state;
    }

    /**
     * Get metrics for the performance controller.
     *
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        return [
            'state' => $this->currentState(),
            'failure_count' => Cache::get(self::CACHE_KEY.':failure_count', 0),
            'opened_at' => Cache::get(self::CACHE_KEY.':opened_at'),
            'half_open_probes_remaining' => $this->halfOpenProbesRemaining(),
        ];
    }

    /**
     * Manually reset the circuit breaker to CLOSED state.
     */
    public function reset(): void
    {
        Cache::forget(self::CACHE_KEY.':state');
        Cache::forget(self::CACHE_KEY.':failure_count');
        Cache::forget(self::CACHE_KEY.':opened_at');
        Cache::forget(self::CACHE_KEY.':half_open_probes');

        Cache::put(self::CACHE_KEY.':state', self::STATE_CLOSED, now()->addDays(1));
        Cache::put(self::CACHE_KEY.':failure_count', 0, now()->addDays(1));
    }

    /**
     * Called when a protected operation succeeds.
     */
    private function onSuccess(): void
    {
        $state = $this->currentState();

        if ($state === self::STATE_HALF_OPEN) {
            $remaining = Cache::decrement(self::CACHE_KEY.':half_open_probes');

            if ($remaining <= 0) {
                $this->transitionToClosed();
            }
        } else {
            Cache::put(self::CACHE_KEY.':failure_count', 0, now()->addMinutes(10));
        }
    }

    /**
     * Called when a protected operation fails.
     */
    private function onFailure(): void
    {
        $state = $this->currentState();

        if ($state === self::STATE_HALF_OPEN) {
            $this->transitionToOpen();
            return;
        }

        $count = Cache::increment(self::CACHE_KEY.':failure_count');

        if ($count >= $this->failureThreshold) {
            $this->transitionToOpen();
        }
    }

    private function transitionToOpen(): void
    {
        Cache::put(self::CACHE_KEY.':state', self::STATE_OPEN, now()->addMinutes(10));
        Cache::put(self::CACHE_KEY.':opened_at', now()->timestamp, now()->addMinutes(10));
        Cache::forget(self::CACHE_KEY.':half_open_probes');

        Log::warning('Circuit breaker OPENED for automation dispatcher');
    }

    private function transitionToHalfOpen(): void
    {
        Cache::put(self::CACHE_KEY.':state', self::STATE_HALF_OPEN, now()->addMinutes(5));
        Cache::put(self::CACHE_KEY.':half_open_probes', $this->halfOpenProbes, now()->addMinutes(5));

        Log::info('Circuit breaker transitioned to HALF_OPEN for automation dispatcher');
    }

    private function transitionToClosed(): void
    {
        Cache::put(self::CACHE_KEY.':state', self::STATE_CLOSED, now()->addDays(1));
        Cache::put(self::CACHE_KEY.':failure_count', 0, now()->addDays(1));
        Cache::forget(self::CACHE_KEY.':opened_at');
        Cache::forget(self::CACHE_KEY.':half_open_probes');

        Log::info('Circuit breaker CLOSED for automation dispatcher');
    }

    /**
     * Atomically acquire a half-open probe slot.
     */
    private function acquireHalfOpenProbe(): bool
    {
        return Cache::decrement(self::CACHE_KEY.':half_open_probes') > 0;
    }

    private function halfOpenProbesRemaining(): int
    {
        $state = $this->currentState();

        if ($state !== self::STATE_HALF_OPEN) {
            return 0;
        }

        return max(0, Cache::get(self::CACHE_KEY.':half_open_probes', 0));
    }
}