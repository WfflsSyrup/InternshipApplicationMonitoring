<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'correlation_id',
    'internship_application_id',
    'direction',
    'key_id',
    'cipher_suite',
    'aad',
    'iv_hex',
    'ciphertext_b64',
    'tag_b64',
    'payload_sha256',
    'signature_verified',
    'replay_checked',
    'verification_status',
    'failure_reason',
    'created_by',
])]
class SecureIntegrationEnvelope extends Model
{
    public const DIRECTION_OUTBOUND = 'outbound_partner_packet';

    public const DIRECTION_INBOUND = 'inbound_partner_callback';

    /**
     * @var array<int, string>
     */
    public const DIRECTIONS = [
        self::DIRECTION_OUTBOUND,
        self::DIRECTION_INBOUND,
    ];

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED_SIGNATURE = 'rejected_signature';

    public const STATUS_REJECTED_TAMPER = 'rejected_tamper';

    public const STATUS_REJECTED_REPLAY = 'rejected_replay';

    public const STATUS_REJECTED_EXPIRED = 'rejected_expired';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED_SIGNATURE,
        self::STATUS_REJECTED_TAMPER,
        self::STATUS_REJECTED_REPLAY,
        self::STATUS_REJECTED_EXPIRED,
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_VERIFIED => 'Verified',
        self::STATUS_REJECTED_SIGNATURE => 'Rejected: Signature',
        self::STATUS_REJECTED_TAMPER => 'Rejected: Tamper',
        self::STATUS_REJECTED_REPLAY => 'Rejected: Replay',
        self::STATUS_REJECTED_EXPIRED => 'Rejected: Expired',
    ];

    /**
     * @var array<string, string>
     */
    public const DIRECTION_LABELS = [
        self::DIRECTION_OUTBOUND => 'Outbound Partner Packet',
        self::DIRECTION_INBOUND => 'Inbound Partner Callback',
    ];

    protected function casts(): array
    {
        return [
            'signature_verified' => 'boolean',
            'replay_checked' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(InternshipApplication::class, 'internship_application_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::STATUS_VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this->verification_status !== self::STATUS_VERIFIED;
    }

    public function isOutbound(): bool
    {
        return $this->direction === self::DIRECTION_OUTBOUND;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->verification_status] ?? 'Unknown';
    }

    public function directionLabel(): string
    {
        return self::DIRECTION_LABELS[$this->direction] ?? (string) $this->direction;
    }

    public function applicationReferenceCode(): ?string
    {
        $reference = $this->application?->reference_code;

        if (filled($reference)) {
            return $reference;
        }

        $parts = explode('|', (string) $this->aad);

        return filled($parts[0] ?? null) ? $parts[0] : null;
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || $status === 'all') {
            return $query;
        }

        return $query->where('verification_status', $status);
    }

    public function scopeWithDirection(Builder $query, ?string $direction): Builder
    {
        if (blank($direction) || $direction === 'all') {
            return $query;
        }

        return $query->where('direction', $direction);
    }

    /**
     * Envelopes are only visible when the underlying application is, mirroring
     * the application RBAC so a sealed packet never widens access.
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
            }),
            User::ROLE_PARTNER_REVIEWER => $query->whereHas('application', static function (Builder $query) use ($user): void {
                $query->where('partner_reviewer_id', $user->getKey());
            }),
            default => $query->whereRaw('1 = 0'),
        };
    }
}