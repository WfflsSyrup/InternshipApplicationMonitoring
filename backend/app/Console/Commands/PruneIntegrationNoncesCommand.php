<?php

namespace App\Console\Commands;

use App\Models\UsedIntegrationNonce;
use App\Support\Crypto\CryptoLogSanitizer;
use Illuminate\Console\Command;

class PruneIntegrationNoncesCommand extends Command
{
    protected $signature = 'integration:prune-nonces';

    protected $description = 'Delete consumed integration nonces that can no longer be replayed (past the clock-skew window).';

    public function handle(): int
    {
        $removed = UsedIntegrationNonce::query()
            ->where('expires_at', '<', now())
            ->delete();

        CryptoLogSanitizer::write('info', [
            'outcome' => 'nonces_pruned',
            'mode' => sprintf('removed=%d', $removed),
            'http_status' => 200,
            'duration_ms' => 0,
        ]);

        $this->info(sprintf('Pruned %d expired integration nonce(s).', $removed));

        return self::SUCCESS;
    }
}