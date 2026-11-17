<?php

namespace App\Console\Commands;

use App\Services\Automation\AutomationDispatcherService;
use App\Services\Automation\AutomationSweepService;
use Illuminate\Console\Command;

class SweepIncompleteCommand extends Command
{
    protected $signature = 'internship:sweep-incomplete
        {--fault-mode= : Controlled fault injection (requires AUTOMATION_ALLOW_FAULT_INJECTION=true)}';

    protected $description = 'Alert students whose draft or revision-requested application has been incomplete for more than 48 hours.';

    public function handle(AutomationSweepService $sweeps, AutomationDispatcherService $dispatcher): int
    {
        $requested = $this->option('fault-mode');
        $faultMode = is_string($requested) && $requested !== '' ? $requested : null;

        if ($faultMode !== null && $dispatcher->resolveFaultMode($faultMode) === null) {
            $this->error('Fault injection is unavailable for this environment.');

            return self::FAILURE;
        }

        $summary = $sweeps->runIncomplete($dispatcher->resolveFaultMode($faultMode));

        $this->renderSummary($summary);

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function renderSummary(array $summary): void
    {
        $dispatch = is_array($summary['dispatch'] ?? null) ? $summary['dispatch'] : [];

        $this->table(['Metric', 'Value'], [
            ['Scanned', (string) ($summary['scanned'] ?? 0)],
            ['Jobs enqueued', (string) ($summary['enqueued'] ?? 0)],
            ['Duplicates skipped', (string) ($summary['duplicates_skipped'] ?? 0)],
            ['Dispatched', (string) ($dispatch['processed'] ?? 0)],
            ['Succeeded', (string) ($dispatch['succeeded'] ?? 0)],
            ['Retry scheduled', (string) ($dispatch['retry_scheduled'] ?? 0)],
            ['Manual review required', (string) ($dispatch['manual_review_required'] ?? 0)],
        ]);
    }
}