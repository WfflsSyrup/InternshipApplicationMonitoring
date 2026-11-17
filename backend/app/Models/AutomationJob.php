<?php

namespace App\Models;

use Database\Factories\AutomationJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'correlation_id',
    'idempotency_key',
    'internship_application_id',
    'automation_type',
    'recipient_role',
    'recipient_identifier',
    'payload',
    'status',
    'attempts',
    'max_attempts',
    'failure_class',
    'last_error_code',
    'last_error_summary',
    'next_retry_at',
    'completed_at',
    'coordinator_remarks',
])]
class AutomationJob extends Model
{
    /** @use HasFactory<AutomationJobFactory> */
    use HasFactory;

    public const TYPE_MILESTONE_REMINDER = 'milestone_reminder';

    public const TYPE_INCOMPLETE_RECORD_ALERT = 'incomplete_record_alert';

    public const TYPE_STALLED_ESCALATION = 'stalled_escalation';

    public const TYPE_WEEKLY_SUMMARY = 'weekly_summary';

    public const TYPES = [
        self::TYPE_MILESTONE_REMINDER,
        self::TYPE_INCOMPLETE_RECORD_ALERT,
        self::TYPE_STALLED_ESCALATION,
        self::TYPE_WEEKLY_SUMMARY,
    ];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_MANUAL_REVIEW_REQUIRED = 'manual_review_required';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_RETRYING,
        self::STATUS_SUCCEEDED,
        self::STATUS_MANUAL_REVIEW_REQUIRED,
    ];

    public const FAILURE_TRANSIENT = 'transient';

    public const FAILURE_PERMANENT = 'permanent';

    public const FAILURE_CLASSES = [
        self::FAILURE_TRANSIENT,
        self::FAILURE_PERMANENT,
    ];

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        self::TYPE_MILESTONE_REMINDER => 'Milestone Reminder',
        self::TYPE_INCOMPLETE_RECORD_ALERT => 'Incomplete Record Alert',
        self::TYPE_STALLED_ESCALATION => 'Stalled Escalation',
        self::TYPE_WEEKLY_SUMMARY => 'Weekly Placement Summary',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_QUEUED => 'Queued',
        self::STATUS_RETRYING => 'Retrying',
        self::STATUS_SUCCEEDED => 'Succeeded',
        self::STATUS_MANUAL_REVIEW_REQUIRED => 'Manual Review Required',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'last_error_code' => 'integer',
            'next_retry_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(InternshipApplication::class, 'internship_application_id');
    }

    public function isCoordinatorVisibleOnly(): bool
    {
        return $this->internship_application_id === null;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RETRYING], true);
    }

    public function needsCoordinatorRecovery(): bool
    {
        return $this->status === self::STATUS_MANUAL_REVIEW_REQUIRED;
    }

    public function attemptsLabel(): string
    {
        return $this->attempts.'/'.$this->max_attempts;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? str_replace('_', ' ', ucfirst((string) $this->status));
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->automation_type]
            ?? str_replace('_', ' ', ucfirst((string) $this->automation_type));
    }

    /**
     * Coordinator-facing recovery guidance. Never contains stack traces or secrets.
     */
    public function guidance(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 'Waiting for the dispatcher to deliver this automation.',
            self::STATUS_RETRYING => 'Delivery failed with a transient error. A bounded retry is scheduled.',
            self::STATUS_SUCCEEDED => 'Delivered successfully by the automation dispatcher.',
            self::STATUS_MANUAL_REVIEW_REQUIRED => match ($this->failure_class) {
                self::FAILURE_PERMANENT => 'Permanent failure (attempt 1 of '.$this->max_attempts.'). '
                    .'Fix the recipient identifier, then requeue this job for recovery.',
                default => 'Retries were exhausted after '.$this->attempts.' of '.$this->max_attempts
                    .' attempts. Requeue this job for recovery once the dispatcher is reachable.',
            },
            default => 'The automation job is in an unknown state.',
        };
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Role-scoped visibility. Coordinators see every job; everyone else only sees
     * jobs linked to an application they are already allowed to view.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            User::ROLE_COORDINATOR => $query,
            User::ROLE_STUDENT => $query->whereHas('application', static function (Builder $query) use ($user): void {
                $query->where('student_id', $user->getKey());
            }),
            User::ROLE_ADVISER => $query->whereHas('application', static function (Builder $query) use ($user): void {
                $query->where('adviser_id', $user->getKey());

                if (filled($user->department_or_company)) {
                    $query->orWhereHas('student', static function (Builder $query) use ($user): void {
                        $query->where('department_or_company', $user->department_or_company);
                    });
                }
            }),
            User::ROLE_PARTNER_REVIEWER => $query->whereHas('application', static function (Builder $query) use ($user): void {
                $query->where('partner_reviewer_id', $user->getKey());
            }),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Jobs that are allowed to run right now: queued (or retrying) with no
     * backoff window left.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_QUEUED, self::STATUS_RETRYING])
            ->where(static function (Builder $query): void {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', Carbon::now());
            });
    }
}