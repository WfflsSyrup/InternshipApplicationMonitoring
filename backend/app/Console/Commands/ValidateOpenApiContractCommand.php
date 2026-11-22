<?php

namespace App\Console\Commands;

use App\Services\Contract\OpenApiContractService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'openapi:validate-contract',
    description: 'Validate OpenAPI 3.1 contract parity and scan for secrets',
)]
class ValidateOpenApiContractCommand extends Command
{
    public function __construct(
        private readonly OpenApiContractService $contractService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Validating OpenAPI 3.1 contract...');
        $this->newLine();

        // 1. Load and validate spec
        try {
            $spec = $this->contractService->loadSpec();
            $this->info('✓ Specification parsed successfully');
            $this->line("  OpenAPI version: {$spec['openapi']}");
            $this->line("  Info version: {$spec['info']['version']}");
            $this->line("  Title: {$spec['info']['title']}");
        } catch (\Throwable $e) {
            $this->error('✗ Failed to load specification: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();

        // 2. Parity audit
        $this->info('Running route parity audit...');
        $parity = $this->contractService->auditParity();

        $this->line("  Documented operations: {$parity['documented_operations']}");
        $this->line("  Missing from spec: " . count($parity['missing_from_spec']));
        $this->line("  Stale in spec: " . count($parity['stale_in_spec']));

        if ($parity['parity_passed']) {
            $this->info('✓ Parity audit PASSED — 100% route alignment');
        } else {
            $this->error('✗ Parity audit FAILED — drift detected');

            if ($parity['missing_from_spec']) {
                $this->newLine();
                $this->warn('Missing from spec (live routes not documented):');
                foreach ($parity['missing_from_spec'] as $missing) {
                    $this->line("  - {$missing}");
                }
            }

            if ($parity['stale_in_spec']) {
                $this->newLine();
                $this->warn('Stale in spec (documented but not live):');
                foreach ($parity['stale_in_spec'] as $stale) {
                    $this->line("  - {$stale}");
                }
            }
        }

        $this->newLine();

        // 3. Secret scan
        $this->info('Scanning specification for secrets...');
        $secretScan = $this->contractService->scanForSecrets();

        if ($secretScan['clean']) {
            $this->info('✓ Secret scan CLEAN — no live keys, passwords, or real PII found');
        } else {
            $this->error('✗ Secret scan FAILED — violations detected');
            foreach ($secretScan['violations'] as $violation) {
                $this->line("  - {$violation}");
            }
        }

        $this->newLine();

        // Summary
        $overallPassed = $parity['parity_passed'] && $secretScan['clean'];

        if ($overallPassed) {
            $this->info('🎉 All contract validations PASSED');
            $this->newLine();
            $this->table(
                ['Check', 'Status'],
                [
                    ['OpenAPI 3.1 Parse', '✓ Pass'],
                    ['Route Parity', '✓ Pass'],
                    ['Secret Scan', '✓ Pass'],
                ]
            );
            return self::SUCCESS;
        } else {
            $this->error('❌ Contract validation FAILED');
            $this->newLine();
            $this->table(
                ['Check', 'Status'],
                [
                    ['OpenAPI 3.1 Parse', '✓ Pass'],
                    ['Route Parity', $parity['parity_passed'] ? '✓ Pass' : '✗ Fail'],
                    ['Secret Scan', $secretScan['clean'] ? '✓ Pass' : '✗ Fail'],
                ]
            );
            return self::FAILURE;
        }
    }
}