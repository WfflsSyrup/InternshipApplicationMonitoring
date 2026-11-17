<?php

namespace App\Services\Crypto;

use App\Exceptions\Crypto\CryptoConfigurationException;
use App\Exceptions\Crypto\CryptoVerificationException;
use App\Models\InternshipApplication;
use App\Models\SecureIntegrationEnvelope;
use App\Models\User;
use App\Support\Crypto\CryptoLogSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envelope encryption (AES-256-GCM with AAD) plus detached HMAC-SHA256 request
 * authenticity for partner-bound internship endorsement packets and inbound
 * partner interview outcome callbacks.
 *
 * Design rules enforced here:
 *  - a fresh 12-byte IV per encryption call, never reused;
 *  - routing metadata ({reference_code}|{key_id}|{direction}) bound as AAD so a
 *    sealed packet cannot be transplanted to another application or direction;
 *  - signature verified (hash_equals) before any decryption is attempted;
 *  - nonces consumed atomically, timestamps bounded by a clock-skew window;
 *  - every attempt (verified or rejected) recorded as an envelope audit row and
 *    as a sanitized single-line audit log entry.
 */
class EnvelopeCryptoService
{
    public const CIPHER_ALG = 'AES-256-GCM';

    public const SIGNATURE_ALG = 'HMAC-SHA256';

    public const CIPHER_SUITE = 'AES-256-GCM+HMAC-SHA256';

    public const DIRECTION_OUTBOUND = 'outbound_partner_packet';

    public const DIRECTION_INBOUND = 'inbound_partner_callback';

    public const VERSION = '1.0';

    /**
     * @var array<string, string>
     */
    public const SIMULATION_LABELS = [
        'valid_callback' => 'Valid callback',
        'tamper_ciphertext' => 'Tampered ciphertext',
        'forged_signature' => 'Forged signature',
        'replay_nonce' => 'Replay attack',
        'expired_timestamp' => 'Expired timestamp',
    ];

    /**
     * @var array<int, string>
     */
    public const SIMULATION_MODES = [
        'valid_callback',
        'tamper_ciphertext',
        'forged_signature',
        'replay_nonce',
        'expired_timestamp',
    ];

    /**
     * @var array<int, string>
     */
    public const CALLBACK_OUTCOMES = [
        'interview_passed',
        'interview_failed',
    ];

    public function activeKeyId(): string
    {
        $active = (string) config('crypto.active_kid', 'v2');

        $this->keyMaterial($active, 'enc');
        $this->keyMaterial($active, 'sig');

        return $active;
    }

    /**
     * Every key id that still holds usable material, newest first.
     *
     * @return array<int, string>
     */
    public function supportedKeyIds(): array
    {
        $supported = [];

        foreach ((array) config('crypto.keys', []) as $kid => $material) {
            $enc = is_array($material) ? ($material['enc'] ?? null) : null;
            $sig = is_array($material) ? ($material['sig'] ?? null) : null;

            if (is_string($enc) && $enc !== '' && is_string($sig) && $sig !== '') {
                $supported[] = (string) $kid;
            }
        }

        usort($supported, static fn (string $a, string $b): int => version_compare($b, $a));

        return array_values(array_unique($supported));
    }

    public function cipherSuite(): string
    {
        return self::CIPHER_SUITE;
    }

    public function isKeyringConfigured(): bool
    {
        try {
            return filled($this->activeKeyId());
        } catch (CryptoConfigurationException) {
            return false;
        }
    }

    public function simulationModes(): array
    {
        return self::SIMULATION_MODES;
    }

    /**
     * Seals an endorsement packet for a partner and returns the canonical
     * envelope, the detached signature headers and the audit record.
     *
     * @param  array<string, mixed>  $sensitivePayload
     * @return array{envelope: array<string, mixed>, headers: array<string, string>, record: SecureIntegrationEnvelope}
     */
    public function sealOutboundPacket(InternshipApplication $application, array $sensitivePayload, ?User $actor = null): array
    {
        $startedAt = microtime(true);
        $keyId = $this->activeKeyId();

        $payload = ['reference_code' => (string) $application->reference_code] + $sensitivePayload;
        $aad = $this->buildAad((string) $application->reference_code, $keyId, self::DIRECTION_OUTBOUND);

        $envelope = $this->encryptPayload($payload, $keyId, $aad);
        $body = $this->encodeEnvelope($envelope);
        $headers = $this->signHeaders('POST', (string) config('crypto.callback_path'), $body, $keyId);

        $record = new SecureIntegrationEnvelope([
            'correlation_id' => (string) Str::uuid(),
            'internship_application_id' => $application->getKey(),
            'direction' => self::DIRECTION_OUTBOUND,
            'key_id' => $keyId,
            'cipher_suite' => self::cipherSuite(),
            'aad' => $aad,
            'iv_hex' => (string) $envelope['iv'],
            'ciphertext_b64' => (string) $envelope['ciphertext'],
            'tag_b64' => (string) $envelope['tag'],
            'payload_sha256' => hash('sha256', (string) json_encode($payload)),
            'signature_verified' => true,
            'replay_checked' => false,
            'verification_status' => SecureIntegrationEnvelope::STATUS_VERIFIED,
            'failure_reason' => null,
            'created_by' => $actor?->getKey(),
        ]);

        $record->save();

        $this->log('sealed', $record, [
            'http_status' => 201,
            'duration_ms' => $this->elapsed($startedAt),
        ]);

        return ['envelope' => $envelope, 'headers' => $headers, 'record' => $record];
    }

    /**
     * Verifies authenticity, freshness, replay and integrity of an inbound
     * integration request, then decrypts the envelope.
     *
     * @param  array<string, mixed>  $headers
     * @return array{envelope: SecureIntegrationEnvelope, payload: array<string, mixed>}
     */
    public function verifyAndUnsealInbound(
        string $method,
        string $path,
        array $headers,
        string $rawBody,
        ?string $correlationId = null,
    ): array {
        $startedAt = microtime(true);
        $correlationId ??= (string) Str::uuid();

        $keyId = $this->header($headers, 'x-key-id');
        $timestamp = $this->header($headers, 'x-timestamp');
        $nonce = $this->header($headers, 'x-nonce');
        $signature = $this->header($headers, 'x-signature-256');

        $decoded = $this->decodeEnvelope($rawBody);

        $record = $this->newInboundRecord($decoded, $keyId, $rawBody);

        if (blank($keyId) || blank($timestamp) || blank($nonce) || blank($signature)) {
            $this->reject($record, $correlationId, 401, SecureIntegrationEnvelope::STATUS_REJECTED_SIGNATURE,
                'Missing integration headers: X-Key-Id, X-Timestamp, X-Nonce and X-Signature-256 are all mandatory.');
        }

        if (! in_array((string) $keyId, $this->supportedKeyIds(), true)) {
            $this->reject($record, $correlationId, 401, SecureIntegrationEnvelope::STATUS_REJECTED_SIGNATURE,
                sprintf('Key id [%s] is not present in the active keyring.', $keyId));
        }

        if ($decoded === null) {
            $this->reject($record, $correlationId, 422, SecureIntegrationEnvelope::STATUS_REJECTED_TAMPER,
                'The request body is not a valid JSON envelope.');
        }

        if ((string) ($decoded['key_id'] ?? '') !== (string) $keyId) {
            $this->reject($record, $correlationId, 422, SecureIntegrationEnvelope::STATUS_REJECTED_TAMPER,
                'Envelope key id does not match the X-Key-Id header.');
        }

        if ((string) ($decoded['alg'] ?? '') !== self::CIPHER_ALG) {
            $this->reject($record, $correlationId, 422, SecureIntegrationEnvelope::STATUS_REJECTED_TAMPER,
                sprintf('Unsupported envelope algorithm [%s].', (string) ($decoded['alg'] ?? 'missing')));
        }

        if ((string) ($decoded['sig_alg'] ?? '') !== self::SIGNATURE_ALG) {
            $this->reject($record, $correlationId, 422, SecureIntegrationEnvelope::STATUS_REJECTED_TAMPER,
                sprintf('Unsupported envelope signature algorithm [%s].', (string) ($decoded['sig_alg'] ?? 'missing')));
        }

        if (! preg_match('/^[A-Za-z0-9\-]{8,64}$/', (string) $nonce)) {
            $this->reject($record, $correlationId, 401, SecureIntegrationEnvelope::STATUS_REJECTED_SIGNATURE,
                'The X-Nonce header must be a UUIDv4 identifier.');
        }

        if (! ctype_digit((string) $timestamp)) {
            $this->reject($record, $correlationId, 401, SecureIntegrationEnvelope::STATUS_REJECTED_EXPIRED,
                'The X-Timestamp header must be a Unix epoch timestamp in seconds.');
        }

        $skew = (int) config('crypto.clock_skew_seconds', 300);

        if (abs(now()->getTimestamp() - (int) $timestamp) > $skew) {
            $this->reject($record, $correlationId, 401, SecureIntegrationEnvelope::STATUS_REJECTED_EXPIRED,
                sprintf('Request timestamp is outside the +/-%d second clock-skew window.', $skew));
        }

        $this->consumeNonce((string) $nonce, (string) $keyId, $record, $correlationId);

        $expected = $this->signatureFor($method, $path, (string) $timestamp, (string) $nonce, (string) $keyId, $rawBody);

        if (! hash_equals($expected, (string) $signature)) {
            $this->reject($record, $correlationId, 401, SecureIntegrationEnvelope::STATUS_REJECTED_SIGNATURE,
                'The HMAC-SHA256 signature does not match this method, path, timestamp, nonce, key id and body.');
        }

        $record->signature_verified = true;
        $record->replay_checked = true;

        $plain = $this->decryptPayload((string) $decoded['iv'], (string) $decoded['ciphertext'], (string) $decoded['tag'], (string) $decoded['aad'], (string) $keyId);

        if ($plain === null) {
            $this->reject($record, $correlationId, 422, SecureIntegrationEnvelope::STATUS_REJECTED_TAMPER,
                'The AES-256-GCM tag does not authenticate this ciphertext and AAD.');
        }

        $payload = json_decode($plain, true);

        if (! is_array($payload)) {
            $this->reject($record, $correlationId, 422, SecureIntegrationEnvelope::STATUS_REJECTED_TAMPER,
                'The decrypted payload is not a JSON object.');
        }

        $aadParts = explode('|', (string) $decoded['aad']);

        if (count($aadParts) !== 3
            || $aadParts[0] !== (string) ($payload['reference_code'] ?? '')
            || $aadParts[1] !== (string) $keyId
            || $aadParts[2] !== self::DIRECTION_INBOUND) {
            $this->reject($record, $correlationId, 422, SecureIntegrationEnvelope::STATUS_REJECTED_TAMPER,
                'The authenticated routing metadata does not match this inbound callback.');
        }

        $record->aad = (string) $decoded['aad'];
        $record->iv_hex = (string) $decoded['iv'];
        $record->ciphertext_b64 = (string) $decoded['ciphertext'];
        $record->tag_b64 = (string) $decoded['tag'];
        $record->payload_sha256 = hash('sha256', $plain);
        $record->verification_status = SecureIntegrationEnvelope::STATUS_VERIFIED;
        $record->failure_reason = null;
        $record->save();

        $this->log('verified', $record, [
            'http_status' => 200,
            'duration_ms' => $this->elapsed($startedAt),
        ]);

        return ['envelope' => $record, 'payload' => $payload];
    }

    /**
     * Builds detached signature headers for a request body.
     *
     * @return array<string, string>
     */
    public function signHeaders(
        string $method,
        string $path,
        string $rawBody,
        ?string $keyId = null,
        ?int $timestamp = null,
        ?string $nonce = null,
    ): array {
        $kid = $keyId ?? $this->activeKeyId();
        $stamp = $timestamp ?? now()->getTimestamp();
        $unique = $nonce ?? (string) Str::uuid();

        $signature = $this->signatureFor($method, $path, (string) $stamp, $unique, $kid, $rawBody);

        return [
            'X-Key-Id' => $kid,
            'X-Timestamp' => (string) $stamp,
            'X-Nonce' => $unique,
            'X-Signature-256' => $signature,
        ];
    }

    /**
     * Builds a fully signed inbound callback request. Used by the verification
     * lab and by the feature tests so the webhook contract has one source of
     * truth.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: array<string, string>}
     */
    public function sealInboundCallback(
        array $payload,
        ?string $keyId = null,
        ?int $timestamp = null,
    ): array {
        $kid = $keyId ?? $this->activeKeyId();
        $path = (string) config('crypto.callback_path');

        $aad = $this->buildAad((string) $payload['reference_code'], $kid, self::DIRECTION_INBOUND);
        $envelope = $this->encryptPayload($payload, $kid, $aad);
        $body = $this->encodeEnvelope($envelope);

        return [$body, $this->signHeaders('POST', $path, $body, $kid, $timestamp)];
    }

    /**
     * Runs a controlled verification lab scenario. The coordinator sees exactly
     * which check accepted or rejected the payload without exposing keys.
     *
     * @return array<string, mixed>
     */
    public function simulate(
        string $mode,
        InternshipApplication $application,
        ?string $outcome = null,
    ): array {
        if (! in_array($mode, self::SIMULATION_MODES, true)) {
            throw new CryptoConfigurationException(sprintf('Unknown simulation mode [%s].', $mode));
        }

        $keyId = $this->activeKeyId();
        $payload = [
            'reference_code' => (string) $application->reference_code,
            'outcome' => in_array($outcome, self::CALLBACK_OUTCOMES, true) ? $outcome : self::CALLBACK_OUTCOMES[0],
            'remarks' => 'Controlled simulation callback issued from the crypto verification lab.',
        ];

        $skew = (int) config('crypto.clock_skew_seconds', 300);
        $path = (string) config('crypto.callback_path');

        return match ($mode) {
            'valid_callback' => $this->simulateValid($path, $payload, $keyId),
            'tamper_ciphertext' => $this->simulateTampered($path, $payload, $keyId),
            'forged_signature' => $this->simulateForged($path, $payload, $keyId),
            'replay_nonce' => $this->simulateReplay($path, $payload, $keyId),
            'expired_timestamp' => $this->simulateExpired($path, $payload, $keyId, $skew),
            default => throw new CryptoConfigurationException(sprintf('Unknown simulation mode [%s].', $mode)),
        };
    }

    /**
     * Re-seals a stored envelope under the active key id.
     */
    public function rotateRecord(SecureIntegrationEnvelope $record): bool
    {
        $activeKid = $this->activeKeyId();

        if ($record->key_id === $activeKid) {
            return false;
        }

        $plain = $this->decryptPayload(
            (string) $record->iv_hex,
            (string) $record->ciphertext_b64,
            (string) $record->tag_b64,
            (string) $record->aad,
            (string) $record->key_id,
        );

        if ($plain === null) {
            return false;
        }

        $payload = json_decode($plain, true);

        if (! is_array($payload)) {
            return false;
        }

        $reference = (string) ($payload['reference_code'] ?? explode('|', (string) $record->aad)[0] ?? '');
        $aad = $this->buildAad($reference, $activeKid, (string) $record->direction);
        $envelope = $this->encryptPayload($payload, $activeKid, $aad);

        $record->key_id = $activeKid;
        $record->aad = $aad;
        $record->iv_hex = (string) $envelope['iv'];
        $record->ciphertext_b64 = (string) $envelope['ciphertext'];
        $record->tag_b64 = (string) $envelope['tag'];
        $record->payload_sha256 = hash('sha256', $plain);
        $record->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) !== 0) {
                continue;
            }

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if (! is_string($value)) {
                return null;
            }

            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeEnvelope(string $rawBody): ?array
    {
        $decoded = json_decode($rawBody, true);

        if (! is_array($decoded)) {
            return null;
        }

        if (! is_string($decoded['iv'] ?? null)
            || ! is_string($decoded['ciphertext'] ?? null)
            || ! is_string($decoded['tag'] ?? null)
            || ! is_string($decoded['aad'] ?? null)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>|null  $decoded
     */
    private function newInboundRecord(?array $decoded, ?string $keyId, string $rawBody): SecureIntegrationEnvelope
    {
        $aad = is_string($decoded['aad'] ?? null) ? $decoded['aad'] : '';
        $iv = is_string($decoded['iv'] ?? null) ? $decoded['iv'] : '';
        $ciphertext = is_string($decoded['ciphertext'] ?? null) ? $decoded['ciphertext'] : '';
        $tag = is_string($decoded['tag'] ?? null) ? $decoded['tag'] : '';

        $record = new SecureIntegrationEnvelope([
            'correlation_id' => (string) Str::uuid(),
            'internship_application_id' => $this->applicationIdForAad($aad),
            'direction' => self::DIRECTION_INBOUND,
            'key_id' => $keyId ?: 'unknown',
            'cipher_suite' => self::cipherSuite(),
            'aad' => mb_substr($aad, 0, 190),
            'iv_hex' => mb_substr($iv, 0, 24),
            'ciphertext_b64' => $ciphertext,
            'tag_b64' => mb_substr($tag, 0, 32),
            'payload_sha256' => hash('sha256', $rawBody ?? ''),
            'signature_verified' => false,
            'replay_checked' => false,
            'verification_status' => SecureIntegrationEnvelope::STATUS_REJECTED_TAMPER,
            'failure_reason' => null,
        ]);

        return $record;
    }

    private function applicationIdForAad(string $aad): ?int
    {
        $reference = explode('|', $aad)[0] ?? '';

        if ($reference === '') {
            return null;
        }

        $id = InternshipApplication::query()
            ->where('reference_code', $reference)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function consumeNonce(
        string $nonce,
        string $keyId,
        SecureIntegrationEnvelope $record,
        string $correlationId,
    ): void {
        $expiresAt = now()->addSeconds(max(60, (int) config('crypto.nonce_ttl_seconds', 900)));

        // Atomic and database-agnostic: a duplicate nonce inserts zero rows.
        $inserted = DB::table('used_integration_nonces')->insertOrIgnore([
            'nonce' => $nonce,
            'key_id' => $keyId,
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);

        if ((int) $inserted === 0) {
            $this->reject($record, $correlationId, 409, SecureIntegrationEnvelope::STATUS_REJECTED_REPLAY,
                'This X-Nonce has already been used. Replay attempts are rejected.');
        }

        $record->replay_checked = true;
    }


    private function signatureFor(string $method, string $path, string $timestamp, string $nonce, string $keyId, string $rawBody): string
    {
        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $keyId,
            hash('sha256', $rawBody),
        ]);

        return (string) config('crypto.signature_prefix', 'sha256=')
            .hash_hmac('sha256', $canonical, $this->keyMaterial($keyId, 'sig'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function encryptPayload(array $payload, string $keyId, string $aad): array
    {
        $iv = random_bytes((int) config('crypto.iv_length_bytes', 12));
        $tag = '';

        $ciphertext = openssl_encrypt(
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            strtolower(self::CIPHER_ALG),
            $this->keyMaterial($keyId, 'enc'),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            (int) config('crypto.tag_length_bytes', 16),
        );

        if ($ciphertext === false || strlen($tag) !== (int) config('crypto.tag_length_bytes', 16)) {
            throw new CryptoConfigurationException('AES-256-GCM encryption failed for the current keyring.');
        }

        return [
            'version' => (string) config('crypto.envelope_version', '1.0'),
            'key_id' => $keyId,
            'alg' => self::CIPHER_ALG,
            'sig_alg' => self::SIGNATURE_ALG,
            'aad' => $aad,
            'iv' => bin2hex($iv),
            'ciphertext' => base64_encode($ciphertext),
            'tag' => base64_encode($tag),
        ];
    }

    private function decryptPayload(string $ivHex, string $ciphertextB64, string $tagB64, string $aad, string $keyId): ?string
    {
        if (! preg_match('/^[0-9a-f]{24}$/', $ivHex)) {
            return null;
        }

        $iv = hex2bin($ivHex);
        $ciphertext = base64_decode($ciphertextB64, true);
        $tag = base64_decode($tagB64, true);

        if ($iv === false || $ciphertext === false || $tag === false || strlen($tag) !== 16 || strlen($ciphertext) < 1) {
            return null;
        }

        $plain = openssl_decrypt(
            $ciphertext,
            strtolower(self::CIPHER_ALG),
            $this->keyMaterial($keyId, 'enc'),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
        );

        return $plain === false ? null : $plain;
    }

    private function buildAad(string $referenceCode, string $keyId, string $direction): string
    {
        return implode('|', [$referenceCode, $keyId, $direction]);
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function encodeEnvelope(array $envelope): string
    {
        return (string) json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function keyMaterial(string $keyId, string $purpose): string
    {
        $configured = config("crypto.keys.$keyId.$purpose");

        if (! is_string($configured) || $configured === '') {
            throw new CryptoConfigurationException(sprintf(
                'Cryptographic key material for [%s/%s] is not configured. Set the matching CRYPTO_KEY_* environment variable.',
                $keyId,
                $purpose,
            ));
        }

        $decoded = base64_decode($configured, true);

        if ($decoded === false || strlen($decoded) !== 32) {
            throw new CryptoConfigurationException(sprintf(
                'Cryptographic key material for [%s/%s] must be a base64-encoded 256-bit value.',
                $keyId,
                $purpose,
            ));
        }

        return $decoded;
    }

    private function reject(
        SecureIntegrationEnvelope $record,
        string $correlationId,
        int $status,
        string $reasonCode,
        string $message,
    ): never {
        $record->correlation_id = $correlationId;
        $record->verification_status = $reasonCode;
        $record->failure_reason = mb_substr($message, 0, 190);
        $record->save();

        $this->log($reasonCode, $record, [
            'http_status' => $status,
            'duration_ms' => 0,
        ]);

        throw new CryptoVerificationException($status, $reasonCode, $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateValid(string $path, array $payload, string $keyId): array
    {
        [$body, $headers] = $this->sealInboundCallback($payload, $keyId);
        $attempt = $this->attempt($path, $headers, $body);

        return $this->simulationResult('valid_callback', $payload, $keyId, $attempt, [
            ['name' => 'X-Key-Id', 'result' => 'known key', 'detail' => $keyId],
            ['name' => 'X-Timestamp', 'result' => 'inside skew window', 'detail' => $headers['X-Timestamp']],
            ['name' => 'X-Nonce', 'result' => 'consumed once', 'detail' => 'nonce row inserted'],
            ['name' => 'X-Signature-256', 'result' => 'verified', 'detail' => 'hash_equals match'],
            ['name' => 'AAD', 'result' => 'bound', 'detail' => 'reference, key id and inbound direction'],
            ['name' => 'AES-256-GCM tag', 'result' => 'verified', 'detail' => 'openssl_decrypt succeeded'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateTampered(string $path, array $payload, string $keyId): array
    {
        [$body, $headers] = $this->sealInboundCallback($payload, $keyId);

        $envelope = json_decode($body, true);
        $raw = base64_decode((string) $envelope['ciphertext'], true);
        $last = strlen($raw) - 1;
        $raw[$last] = chr(ord($raw[$last]) ^ 0x01);
        $envelope['ciphertext'] = base64_encode($raw);

        $body = (string) json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = $this->signHeaders('POST', $path, $body, $keyId);

        $attempt = $this->attempt($path, $headers, $body);

        return $this->simulationResult('tamper_ciphertext', $payload, $keyId, $attempt, [
            ['name' => 'X-Key-Id', 'result' => 'known key', 'detail' => $keyId],
            ['name' => 'X-Signature-256', 'result' => 'verified', 'detail' => 'signature covers the tampered body'],
            ['name' => 'AAD', 'result' => 'bound', 'detail' => 'unchanged routing metadata'],
            ['name' => 'AES-256-GCM tag', 'result' => 'rejected', 'detail' => 'last ciphertext byte flipped'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateForged(string $path, array $payload, string $keyId): array
    {
        [$body, $headers] = $this->sealInboundCallback($payload, $keyId);

        $headers['X-Signature-256'] = (string) config('crypto.signature_prefix', 'sha256=').hash('sha256', 'forged-by-simulation');

        $attempt = $this->attempt($path, $headers, $body);

        return $this->simulationResult('forged_signature', $payload, $keyId, $attempt, [
            ['name' => 'X-Key-Id', 'result' => 'known key', 'detail' => $keyId],
            ['name' => 'X-Signature-256', 'result' => 'rejected', 'detail' => 'signed with an unknown HMAC secret'],
            ['name' => 'AES-256-GCM tag', 'result' => 'not reached', 'detail' => 'decryption is gated behind signature verification'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateReplay(string $path, array $payload, string $keyId): array
    {
        [$body, $headers] = $this->sealInboundCallback($payload, $keyId);

        $first = $this->attempt($path, $headers, $body);
        $second = $this->attempt($path, $headers, $body);

        $result = $this->simulationResult('replay_nonce', $payload, $keyId, $second, [
            ['name' => 'first delivery', 'result' => 'verified', 'detail' => 'nonce consumed'],
            ['name' => 'second delivery', 'result' => 'rejected_replay', 'detail' => 'nonce unique index violation'],
        ]);

        $result['first_attempt'] = [
            'accepted' => $first['accepted'],
            'http_status' => $first['http_status'],
            'reason_code' => $first['reason_code'],
            'envelope_id' => $first['envelope_id'],
        ];

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateExpired(string $path, array $payload, string $keyId, int $skew): array
    {
        $stale = now()->subSeconds(($skew * 2) + 60)->getTimestamp();

        [$body, $headers] = $this->sealInboundCallback($payload, $keyId, $stale);

        $attempt = $this->attempt($path, $headers, $body);

        return $this->simulationResult('expired_timestamp', $payload, $keyId, $attempt, [
            ['name' => 'X-Key-Id', 'result' => 'known key', 'detail' => $keyId],
            ['name' => 'X-Timestamp', 'result' => 'rejected', 'detail' => sprintf('%d seconds in the past, limit is %d', now()->getTimestamp() - $stale, $skew)],
            ['name' => 'X-Signature-256', 'result' => 'not reached', 'detail' => 'freshness is validated before authenticity'],
            ['name' => 'AES-256-GCM tag', 'result' => 'not reached', 'detail' => 'decryption is gated behind freshness'],
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function attempt(string $path, array $headers, string $body): array
    {
        $startedAt = microtime(true);
        $correlationId = 'SIM-'.str_replace('-', '', (string) Str::uuid());

        try {
            $result = $this->verifyAndUnsealInbound('POST', $path, $headers, $body, $correlationId);

            return [
                'accepted' => true,
                'http_status' => 200,
                'reason_code' => null,
                'message' => 'Signature, nonce, AAD and AES-256-GCM tag all verified.',
                'envelope_id' => $result['envelope']->getKey(),
                'correlation_id' => $correlationId,
                'duration_ms' => $this->elapsed($startedAt),
            ];
        } catch (CryptoVerificationException $e) {
            $record = SecureIntegrationEnvelope::query()
                ->where('correlation_id', $correlationId)
                ->first();

            return [
                'accepted' => false,
                'http_status' => $e->status(),
                'reason_code' => $e->reasonCode(),
                'message' => $e->getMessage(),
                'envelope_id' => $record?->getKey(),
                'correlation_id' => $correlationId,
                'duration_ms' => $this->elapsed($startedAt),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $attempt
     * @param  array<int, array<string, string>>  $steps
     * @return array<string, mixed>
     */
    private function simulationResult(
        string $mode,
        array $payload,
        string $keyId,
        array $attempt,
        array $steps,
    ): array {
        $expectedAccepted = $mode === 'valid_callback';

        return [
            'mode' => $mode,
            'label' => self::SIMULATION_LABELS[$mode] ?? $mode,
            'expected_outcome' => $expectedAccepted ? 'accepted' : 'rejected',
            'accepted' => (bool) $attempt['accepted'],
            'matched_expectation' => (bool) $attempt['accepted'] === $expectedAccepted,
            'http_status' => (int) $attempt['http_status'],
            'reason_code' => $attempt['reason_code'],
            'message' => (string) $attempt['message'],
            'reference_code' => (string) ($payload['reference_code'] ?? ''),
            'outcome' => (string) ($payload['outcome'] ?? ''),
            'key_id' => $keyId,
            'cipher_suite' => self::cipherSuite(),
            'correlation_id' => (string) $attempt['correlation_id'],
            'envelope_id' => $attempt['envelope_id'],
            'duration_ms' => (int) $attempt['duration_ms'],
            'steps' => $steps,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function log(string $outcome, SecureIntegrationEnvelope $record, array $extra): void
    {
        CryptoLogSanitizer::write('info', [
            'correlation_id' => $record->correlation_id,
            'envelope_id' => $record->getKey(),
            'direction' => $record->direction,
            'application_ref' => $record->applicationReferenceCode(),
            'key_id' => $record->key_id,
            'cipher' => $record->cipher_suite,
            'outcome' => $outcome,
        ] + $extra);
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}