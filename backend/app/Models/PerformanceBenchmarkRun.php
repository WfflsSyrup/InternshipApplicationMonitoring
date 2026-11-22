<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Week 16 — Module 16 / SA10: controlled benchmark telemetry.
 *
 * @property int $id
 * @property string $correlation_id
 * @property string $scenario_name
 * @property int $virtual_users
 * @property int $total_requests
 * @property int $success_count
 * @property int $conflict_or_throttled_count
 * @property int $error_count
 * @property float $p50_ms
 * @property float $p95_ms
 * @property float $p99_ms
 * @property float $avg_queries_per_req
 * @property float $throughput_rps
 * @property bool $slo_passed
 * @property int|null $executed_by
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class PerformanceBenchmarkRun extends Model
{
    public const SCENARIO_READ_PIPELINE_INDEXED = 'read_pipeline_indexed';
    public const SCENARIO_OPTIMISTIC_CONCURRENCY_RACE = 'optimistic_concurrency_race';
    public const SCENARIO_BURST_MIXED_WORKLOAD = 'burst_mixed_workload';

    public const SCENARIOS = [
        self::SCENARIO_READ_PIPELINE_INDEXED,
        self::SCENARIO_OPTIMISTIC_CONCURRENCY_RACE,
        self::SCENARIO_BURST_MIXED_WORKLOAD,
    ];

    protected $fillable = [
        'correlation_id',
        'scenario_name',
        'virtual_users',
        'total_requests',
        'success_count',
        'conflict_or_throttled_count',
        'error_count',
        'p50_ms',
        'p95_ms',
        'p99_ms',
        'avg_queries_per_req',
        'throughput_rps',
        'slo_passed',
        'executed_by',
    ];

    protected function casts(): array
    {
        return [
            'virtual_users' => 'integer',
            'total_requests' => 'integer',
            'success_count' => 'integer',
            'conflict_or_throttled_count' => 'integer',
            'error_count' => 'integer',
            'p50_ms' => 'float',
            'p95_ms' => 'float',
            'p99_ms' => 'float',
            'avg_queries_per_req' => 'float',
            'throughput_rps' => 'float',
            'slo_passed' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public const UPDATED_AT = null;

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function scopeWithScenario(Builder $query, ?string $scenario): Builder
    {
        if (blank($scenario)) {
            return $query;
        }

        return $query->where('scenario_name', $scenario);
    }

    public function scenarioLabel(): string
    {
        return match ($this->scenario_name) {
            self::SCENARIO_READ_PIPELINE_INDEXED => 'Read Pipeline Indexed',
            self::SCENARIO_OPTIMISTIC_CONCURRENCY_RACE => 'Optimistic Concurrency Race',
            self::SCENARIO_BURST_MIXED_WORKLOAD => 'Burst Mixed Workload',
            default => str($this->scenario_name)->headline()->toString(),
        };
    }

    public function sloLabel(): string
    {
        return $this->slo_passed ? 'PASSED' : 'FAILED';
    }
}