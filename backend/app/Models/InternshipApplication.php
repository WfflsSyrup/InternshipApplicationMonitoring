<?php

namespace App\Models;

use App\Support\ApplicationStatusMachine;
use Database\Factories\InternshipApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference_code',
    'student_id',
    'adviser_id',
    'partner_reviewer_id',
    'host_company_name',
    'target_role',
    'internship_hours_required',
    'target_start_date',
    'requirements_checklist',
    'status',
    'version',
    'interview_schedule',
    'interview_notes',
    'placement_confirmed_at',
    'placement_acknowledged_at',
])]
class InternshipApplication extends Model
{
    /** @use HasFactory<InternshipApplicationFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_REQUIREMENTS_VERIFIED = 'requirements_verified';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    public const STATUS_ENDORSED = 'endorsed';

    public const STATUS_PARTNER_SUBMITTED = 'partner_submitted';

    public const STATUS_INTERVIEW_SCHEDULED = 'interview_scheduled';

    public const STATUS_INTERVIEW_PASSED = 'interview_passed';

    public const STATUS_INTERVIEW_FAILED = 'interview_failed';

    public const STATUS_PLACEMENT_CONFIRMED = 'placement_confirmed';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_REQUIREMENTS_VERIFIED,
        self::STATUS_REVISION_REQUESTED,
        self::STATUS_ENDORSED,
        self::STATUS_PARTNER_SUBMITTED,
        self::STATUS_INTERVIEW_SCHEDULED,
        self::STATUS_INTERVIEW_PASSED,
        self::STATUS_INTERVIEW_FAILED,
        self::STATUS_PLACEMENT_CONFIRMED,
        self::STATUS_WITHDRAWN,
        self::STATUS_REJECTED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_REQUIREMENTS_VERIFIED,
        self::STATUS_REVISION_REQUESTED,
        self::STATUS_ENDORSED,
        self::STATUS_PARTNER_SUBMITTED,
        self::STATUS_INTERVIEW_SCHEDULED,
        self::STATUS_INTERVIEW_PASSED,
        self::STATUS_INTERVIEW_FAILED,
    ];

    public const PARTNER_VISIBLE_STATUSES = [
        self::STATUS_PARTNER_SUBMITTED,
        self::STATUS_INTERVIEW_SCHEDULED,
        self::STATUS_INTERVIEW_PASSED,
        self::STATUS_INTERVIEW_FAILED,
        self::STATUS_PLACEMENT_CONFIRMED,
    ];

    public const STUDENT_EDITABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVISION_REQUESTED,
    ];

    public const DEFAULT_REQUIREMENTS_CHECKLIST = [
        'resume_ready' => false,
        'endorsement_letter' => false,
        'parent_consent' => false,
        'medical_clearance' => false,
    ];

    protected function casts(): array
    {
        return [
            'requirements_checklist' => 'array',
            'internship_hours_required' => 'integer',
            'target_start_date' => 'date',
            'interview_schedule' => 'datetime',
            'placement_confirmed_at' => 'datetime',
            'placement_acknowledged_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }

    public function partnerReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_reviewer_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class)->orderBy('created_at')->orderBy('id');
    }

    public function automationJobs(): HasMany
    {
        return $this->hasMany(AutomationJob::class)->latest('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            User::ROLE_COORDINATOR => $query,
            User::ROLE_STUDENT => $query->where('student_id', $user->getKey()),
            User::ROLE_ADVISER => $query->where(function (Builder $query) use ($user): void {
                $query->where('adviser_id', $user->getKey());

                if (filled($user->department_or_company)) {
                    $query->orWhereHas('student', static function (Builder $query) use ($user): void {
                        $query->where('department_or_company', $user->department_or_company);
                    });
                }
            }),
            User::ROLE_PARTNER_REVIEWER => $query
                ->where('partner_reviewer_id', $user->getKey())
                ->whereIn('status', self::PARTNER_VISIBLE_STATUSES),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function isEditableByStudent(): bool
    {
        return in_array($this->status, self::STUDENT_EDITABLE_STATUSES, true);
    }

    public function isPlacementAcknowledged(): bool
    {
        return $this->placement_acknowledged_at !== null;
    }

    public function statusLabel(): string
    {
        return ApplicationStatusMachine::label($this->status);
    }

    public function checklistComplete(): bool
    {
        $checklist = array_merge(self::DEFAULT_REQUIREMENTS_CHECKLIST, $this->requirements_checklist ?? []);

        return ! in_array(false, array_values($checklist), true);
    }
}