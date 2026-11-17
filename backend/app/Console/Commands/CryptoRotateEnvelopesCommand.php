<?php

namespace App\Console\Commands;

use App\Models\SecureIntegrationEnvelope;
use App\Services\Crypto\EnvelopeCryptoService;
use App\Support\Crypto\CryptoLogSanitizer;
use Illuminate\Console\Command;

class CryptoRotateEnvelopesCommand extends Command
{
    protected $signature = 'crypto:rotate-envelopes {--dry-run : Report what would be re-sealed without writing}';

    protected $description = 'Re-encrypt stored integration envelopes that still use a retired key id.';

    public function handle(EnvelopeCryptoService $crypto): int
    {
        $startedAt = microtime(true);
        $activeKid = $crypto->activeKeyId();
        $batchSize = max(1, (int) config('crypto.rotation.batch_size', 200));

        $pending = SecureIntegrationEnvelope::query()
            ->where('key_id', '!=', $activeKid)
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $this->info(sprintf('Active key id: [%s]. Envelopes awaiting rotation: %d.', $activeKid, $pending->count()));

        if ($pending->isEmpty()) {
            $this->comment('Nothing to rotate.');

            return self::SUCCESS;
        }

        $rotated = 0;
        $failed = 0;

        foreach ($pending as $record) {
            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '  would re-seal envelope #%d from [%s] to [%s]',
                    $record->getKey(),
                    $record->key_id,
                    $activeKid,
                ));

                continue;
            }

            if ($crypto->rotateRecord($record)) {
                $rotated++;
                $this->line(sprintf('  re-sealed envelope #%d onto [%s]', $record->getKey(), $activeKid));

                CryptoLogSanitizer::write('info', [
                    'outcome' => 'rotated',
                    'envelope_id' => $record->getKey(),
                    'direction' => $record->direction,
                    'application_ref' => $record->applicationReferenceCode(),
                    'key_id' => $record->key_id,
                    'cipher' => $record->cipher_suite,
                    'http_status' => 200,
                    'duration_ms' => 0,
                ]);

                continue;
            }

            $failed++;
            $this->error(sprintf('  could not re-seal envelope #%d (missing key material or corrupt ciphertext)', $record->getKey()));

            CryptoLogSanitizer::write('warning', [
                'outcome' => 'rotation_failed',
                'envelope_id' => $record->getKey(),
                'direction' => $record->direction,
                'application_ref' => $record->applicationReferenceCode(),
                'key_id' => $record->key_id,
                'cipher' => $record->cipher_suite,
                'http_status' => 500,
                'duration_ms' => 0,
            ]);
        }

        CryptoLogSanitizer::write('info', [
            'outcome' => $this->option('dry-run') ? 'rotation_dry_run' : 'rotation_completed',
            'key_id' => $activeKid,
            'cipher' => EnvelopeCryptoService::CIPHER_SUITE,
            'http_status' => 200,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'mode' => sprintf('rotated=%d failed=%d dry_run=%d', $rotated, $failed, $this->option('dry-run') ? 1 : 0),
        ]);

        $this->info(sprintf('Rotation finished: %d re-sealed, %d failed.', $rotated, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}