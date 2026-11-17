<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crypto\DispatchPartnerPacketRequest;
use App\Http\Requests\Crypto\VerifyCryptoSimulationRequest;
use App\Http\Resources\InternshipApplicationResource;
use App\Http\Resources\SecureIntegrationEnvelopeResource;
use App\Models\InternshipApplication;
use App\Models\SecureIntegrationEnvelope;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\Crypto\EnvelopeCryptoService;
use App\Support\ApiResponse;
use App\Support\Crypto\CryptoLogSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecureIntegrationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EnvelopeCryptoService $crypto,
        private readonly ApplicationWorkflowService $workflow,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SecureIntegrationEnvelope::class);

        $envelopes = SecureIntegrationEnvelope::query()
            ->visibleTo($request->user())
            ->with('application')
            ->withStatus($request->query('status'))
            ->withDirection($request->query('direction'))
            ->when(
                $request->filled('application_id'),
                static fn ($query) => $query->where('internship_application_id', (int) $request->query('application_id')),
            )
            ->latest('id')
            ->limit(200)
            ->get();

        return self::success(
            SecureIntegrationEnvelopeResource::collection($envelopes),
            'Cryptographic envelope metadata retrieved successfully.',
        );
    }

    public function keyringStatus(): JsonResponse
    {
        $this->authorize('manage', SecureIntegrationEnvelope::class);

        $activeKid = $this->crypto->activeKeyId();

        return self::success([
            'active_kid' => $activeKid,
            'supported_kids' => $this->crypto->supportedKeyIds(),
            'cipher_suite' => $this->crypto->cipherSuite(),
            'signature_algorithm' => EnvelopeCryptoService::SIGNATURE_ALG,
            'iv_length_bytes' => (int) config('crypto.iv_length_bytes', 12),
            'tag_length_bytes' => (int) config('crypto.tag_length_bytes', 16),
            'clock_skew_seconds' => (int) config('crypto.clock_skew_seconds', 300),
            'tamper_simulation_enabled' => (bool) config('crypto.allow_tamper_simulation'),
            'key_material_exposed' => false,
            'client_keys_embedded' => 0,
            'totals' => [
                'envelopes' => SecureIntegrationEnvelope::query()->count(),
                'verified' => SecureIntegrationEnvelope::query()
                    ->where('verification_status', SecureIntegrationEnvelope::STATUS_VERIFIED)
                    ->count(),
                'rejected' => SecureIntegrationEnvelope::query()
                    ->where('verification_status', '!=', SecureIntegrationEnvelope::STATUS_VERIFIED)
                    ->count(),
            ],
        ], 'Cryptographic keyring status retrieved successfully.');
    }

    /**
     * Coordinator-only: seals an endorsement packet for the partner with
     * AES-256-GCM + HMAC-SHA256 and stores the verified envelope record.
     */
    public function dispatchPartnerPacket(DispatchPartnerPacketRequest $request): JsonResponse
    {
        $this->authorize('manage', SecureIntegrationEnvelope::class);

        $application = $request->application();

        if (! in_array($application->status, [
            InternshipApplication::STATUS_ENDORSED,
            InternshipApplication::STATUS_PARTNER_SUBMITTED,
        ], true)) {
            return self::error(
                'Only endorsed or partner-submitted applications can be sealed into a partner packet.',
                422,
                ['application_id' => ['The application must be endorsed or submitted to the partner first.']],
            );
        }

        $result = $this->crypto->sealOutboundPacket(
            $application,
            $this->partnerPacketPayload($application, $request->validated('remarks')),
            $request->user(),
        );

        $record = $result['record']->load('application');

        return self::success(
            new SecureIntegrationEnvelopeResource($record),
            'Partner endorsement packet sealed with AES-256-GCM and signed with HMAC-SHA256.',
            201,
        );
    }

    /**
     * Public webhook: verifies X-Key-Id / X-Timestamp / X-Nonce /
     * X-Signature-256, rejects replays, decrypts the envelope and applies the
     * interview outcome through the workflow service.
     */
    public function partnerCallback(Request $request): JsonResponse
    {
        $path = '/'.ltrim($request->getPathInfo(), '/');

        $result = $this->crypto->verifyAndUnsealInbound(
            $request->getMethod(),
            $path,
            $request->headers->all(),
            $request->getContent(),
        );

        /** @var SecureIntegrationEnvelope $envelope */
        $envelope = $result['envelope'];

        /** @var array<string, mixed> $payload */
        $payload = $result['payload'];

        $application = InternshipApplication::query()
            ->where('reference_code', (string) $payload['reference_code'])
            ->first();

        if ($application === null) {
            CryptoLogSanitizer::write('warning', [
                'outcome' => 'unknown_reference',
                'envelope_id' => $envelope->getKey(),
                'direction' => $envelope->direction,
                'application_ref' => (string) $payload['reference_code'],
                'key_id' => $envelope->key_id,
                'cipher' => $envelope->cipher_suite,
                'http_status' => 404,
                'duration_ms' => 0,
            ]);

            return self::error(
                'The signed callback references an unknown internship application.',
                404,
                ['reference_code' => ['No application matches this reference code.']],
            );
        }

        $outcome = (string) $payload['outcome'];

        if (! in_array($outcome, EnvelopeCryptoService::CALLBACK_OUTCOMES, true)) {
            return self::error(
                'The signed callback contains an unsupported interview outcome.',
                422,
                ['outcome' => ['Outcome must be interview_passed or interview_failed.']],
            );
        }

        $target = $outcome === 'interview_passed'
            ? InternshipApplication::STATUS_INTERVIEW_PASSED
            : InternshipApplication::STATUS_INTERVIEW_FAILED;

        $actor = $application->partnerReviewer ?? $this->coordinatorFallback();

        $updated = $this->workflow->transition($application, $actor, [
            'target_status' => $target,
            'remarks' => filled($payload['remarks'] ?? null)
                ? (string) $payload['remarks']
                : 'Outcome received through the signed partner callback.',
        ]);

        return self::success([
            'application' => new InternshipApplicationResource($updated->load(['student', 'adviser', 'partnerReviewer'])),
            'envelope' => new SecureIntegrationEnvelopeResource($envelope->load('application')),
            'applied_transition' => [
                'outcome' => $outcome,
                'from_status' => $application->getOriginal('status'),
                'to_status' => $updated->status,
            ],
        ], 'Signed partner callback verified and applied.');
    }

    /**
     * Coordinator-only controlled lab: runs one cryptographic scenario so the
     * rejection paths can be demonstrated from the web client.
     */
    public function verifySimulation(VerifyCryptoSimulationRequest $request): JsonResponse
    {
        $this->authorize('manage', SecureIntegrationEnvelope::class);

        if (! config('crypto.allow_tamper_simulation')) {
            return self::error(
                'Controlled tamper simulation is disabled for this environment.',
                422,
                ['mode' => ['Set CRYPTO_ALLOW_TAMPER_SIMULATION=true to enable the verification lab.']],
            );
        }

        $data = $request->validated();

        $application = $this->resolveSimulationApplication($data['reference_code'] ?? null);

        $result = $this->crypto->simulate(
            (string) $data['mode'],
            $application,
            $data['outcome'] ?? null,
        );

        return self::success($result, 'Controlled crypto verification finished.');
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerPacketPayload(InternshipApplication $application, ?string $remarks): array
    {
        $application->loadMissing(['student', 'adviser', 'partnerReviewer']);

        return [
            'student_name' => $application->student?->name,
            'student_number' => $application->student?->student_or_employee_id,
            'host_company_name' => $application->host_company_name,
            'target_role' => $application->target_role,
            'internship_hours_required' => (int) $application->internship_hours_required,
            'target_start_date' => $application->target_start_date?->toDateString(),
            'status' => $application->status,
            'adviser_name' => $application->adviser?->name,
            'partner_reviewer_name' => $application->partnerReviewer?->name,
            'coordinator_remarks' => $remarks,
            'sealed_at' => now()->toIso8601String(),
        ];
    }

    private function resolveSimulationApplication(?string $referenceCode): InternshipApplication
    {
        if (filled($referenceCode)) {
            return InternshipApplication::query()
                ->where('reference_code', (string) $referenceCode)
                ->firstOrFail();
        }

        $eligible = InternshipApplication::query()
            ->whereIn('status', [
                InternshipApplication::STATUS_ENDORSED,
                InternshipApplication::STATUS_PARTNER_SUBMITTED,
            ])
            ->orderBy('id')
            ->first();

        if ($eligible !== null) {
            return $eligible;
        }

        return InternshipApplication::query()->orderBy('id')->firstOrFail();
    }

    private function coordinatorFallback(): User
    {
        $coordinator = User::query()
            ->where('role', User::ROLE_COORDINATOR)
            ->orderBy('id')
            ->first();

        if ($coordinator === null) {
            abort(422, 'No coordinator account is available to record the partner callback.');
        }

        return $coordinator;
    }
}