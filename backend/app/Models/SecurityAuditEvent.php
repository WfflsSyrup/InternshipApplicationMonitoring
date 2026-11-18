<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Week 15 — Module 15 / SA9 security telemetry.
 *
 * @property int $id
 * @property string $correlation_id
 * @property string $event_type
 * @property string $severity
 * @property int|null $actor_id
 * @property string|null $actor_role
 * @property string $ip_hash
 * @property string $endpoint
 * @property int $http_status
 * @property array<string, mixed>|null $details
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class SecurityAuditEvent extends Model
{
    public const EVENT_BOLA_BLOCKED = 'bola_blocked';

    public const EVENT_BFLA_BLOCKED = 'bfla_blocked';

    public const EVENT_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';

    public const EVENT_MASS_ASSIGNMENT_BLOCKED = 'mass_assignment_blocked';

    public const EVENT_PAYLOAD_TOO_LARGE = 'payload_too_large';

    public const EVENT_LOGIN_FAILED = 'login_failed';

    public const EVENT_SESSION_REVOKED = 'session_revoked';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITIES = [
        self::SEVERITY_INFO,
        self::SEVERITY_WARNING,
        self::SEVERITY_HIGH,
    ];

    public const EVENT_TYPES = [
        self::EVENT_BOLA_BLOCKED,
        self::EVENT_BFLA_BLOCKED,
        self::EVENT_RATE_LIMIT_EXCEEDED,
        self::EVENT_MASS_ASSIGNMENT_BLOCKED,
        self::EVENT_PAYLOAD_TOO_LARGE,
        self::EVENT_LOGIN_FAILED,
        self::EVENT_SESSION_REVOKED,
    ];

    protected $fillable = [
        'correlation_id',
        'event_type',
        'severity',
        'actor_id',
        'actor_role',
        'ip_hash',
        'endpoint',
        'http_status',
        'details',
    ];

    protected $hidden = [
        'ip_hash',
    ];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'http_status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeWithEventType(Builder $query, ?string $eventType): Builder
    {
        if (blank($eventType) || $eventType === 'all') {
            return $query;
        }

        return $query->where('event_type', $eventType);
    }

    public function scopeWithSeverity(Builder $query, ?string $severity): Builder
    {
        if (blank($severity) || $severity === 'all') {
            return $query;
        }

        return $query->where('severity', $severity);
    }

    public function severityLabel(): string
    {
        return match ($this->severity) {
            self::SEVERITY_HIGH => 'High',
            self::SEVERITY_WARNING => 'Warning',
            default => 'Info',
        };
    }

    public function eventLabel(): string
    {
        return match ($this->event_type) {
            self::EVENT_BOLA_BLOCKED => 'BOLA Blocked',
            self::EVENT_BFLA_BLOCKED => 'BFLA Blocked',
            self::EVENT_RATE_LIMIT_EXCEEDED => 'Rate Limit Exceeded',
            self::EVENT_MASS_ASSIGNMENT_BLOCKED => 'Mass Assignment Blocked',
            self::EVENT_PAYLOAD_TOO_LARGE => 'Payload Too Large',
            self::EVENT_LOGIN_FAILED => 'Login Failed',
            self::EVENT_SESSION_REVOKED => 'Session Revoked',
            default => str($this->event_type)->headline()->toString(),
        };
    }

    /**
     * Short, non-reversible prefix of the hashed client address. The full
     * hash is never exposed by the API.
     */
    public function ipHashPrefix(int $length = 12): string
    {
        return substr($this->ip_hash, 0, $length);
    }
}