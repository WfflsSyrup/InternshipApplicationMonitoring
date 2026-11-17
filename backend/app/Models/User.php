<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['student_or_employee_id', 'name', 'email', 'password', 'role', 'department_or_company'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_STUDENT = 'student';

    public const ROLE_ADVISER = 'adviser';

    public const ROLE_COORDINATOR = 'coordinator';

    public const ROLE_PARTNER_REVIEWER = 'partner_reviewer';

    public const ROLES = [
        self::ROLE_STUDENT,
        self::ROLE_ADVISER,
        self::ROLE_COORDINATOR,
        self::ROLE_PARTNER_REVIEWER,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isAdviser(): bool
    {
        return $this->role === self::ROLE_ADVISER;
    }

    public function isCoordinator(): bool
    {
        return $this->role === self::ROLE_COORDINATOR;
    }

    public function isPartnerReviewer(): bool
    {
        return $this->role === self::ROLE_PARTNER_REVIEWER;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(InternshipApplication::class, 'student_id');
    }

    public function advisedApplications(): HasMany
    {
        return $this->hasMany(InternshipApplication::class, 'adviser_id');
    }

    public function reviewedApplications(): HasMany
    {
        return $this->hasMany(InternshipApplication::class, 'partner_reviewer_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class, 'actor_id');
    }
}