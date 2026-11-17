<?php

namespace App\Console\Commands;

use App\Services\Automation\AutomationDispatcherService;
use App\Services\Automation\AutomationSweepService;
use Illuminate\Console\Command;

class WeeklySummaryCommand extends Command
{
    protected $signature = 'internship:weekly-summary
        {--fault-mode= : Controlled fault injection (requires AUTOMATION_ALLOW_FAULT_INJECTION=true)}';

    protected $description = 'Dispatch the weekly placement summary digest for the active ISO week to the coordinator.';

    public function handle(AutomationSweepService $sweeps, AutomationDispatcherService $dispatcher): int
    {
        $requested = $this->option('fault-mode');
        $faultMode = is_string($requested) && $requested !== '' ? $requested : null;

        if ($faultMode !== null && $dispatcher->resolveFaultMode($faultMode) === null) {
            $this->error('Fault injection is unavailable for this environment.');

            return self::FAILURE;
        }

        $summary = $sweeps->runWeekly($dispatcher->resolveFaultMode($faultMode));

        $dispatch = is_array($summary['dispatch'] ?? null) ? $summary['dispatch'] : [];
        $week = is_array($summary['summary'] ?? null) ? $summary['summary'] : [];

        $this->table(['Metric', 'Value'], [
            ['ISO week', (string) ($week['week_key'] ?? 'n/a')],
            ['Window', ($week['week_start'] ?? 'n/a').' → '.($week['week_end'] ?? 'n/a')],
            ['Applications this week', (string) ($week['total'] ?? 0)],
            ['Jobs enqueued', (string) ($summary['enqueued'] ?? 0)],
            ['Duplicates skipped', (string) ($summary['duplicates_skipped'] ?? 0)],
            ['Dispatched', (string) ($dispatch['processed'] ?? 0)],
            ['Succeeded', (string) ($dispatch['succeeded'] ?? 0)],
            ['Manual review required', (string) ($dispatch['manual_review_required'] ?? 0)],
        ]);

        if (is_array($week['counts'] ?? null)) {
            $rows = [];

            foreach ($week['counts'] as $status => $total) {
                $rows[] = [(string) $status, (string) $total];
            }

            if ($rows !== []) {
                $this->table(['Status', 'Count'], $rows);
            }
        }

        return self::SUCCESS;
    }
}