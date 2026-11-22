<?php

namespace Tests\Feature;

use App\Models\PerformanceBenchmarkRun;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use App\Services\Contract\OpenApiContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Week 17 — Module 17 / SA11: OpenAPI 3.1 Contract Specification, API Versioning & Developer Integration Portal
 *
 * TC-17-01: project10-v1.openapi.yaml parses as valid OpenAPI 3.1.0 with all required components
 * TC-17-02: Live Laravel /api/v1/* routes achieve 100% parity with project10-v1.openapi.yaml
 * TC-17-03: GET /api/v1/docs/openapi.json attaches X-API-Contract-Version & ETag and supports 304 Not Modified
 * TC-17-04: GET /api/v1/docs/portal returns self-contained 200 text/html documentation
 * TC-17-05: Zero-secret scan confirms no live keys, passwords, or real PII exist in project10-v1.openapi.yaml
 * TC-17-06: Simulated undocumented route triggers drift detection (parity_passed === false)
 * TC-17-07: Non-Coordinator (student) is denied GET /api/v1/docs/contract-audit with 403 Forbidden
 */
class Week17OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_tc_17_01_openapi_spec_parses_as_valid_310_with_required_components(): void
    {
        $service = new OpenApiContractService();
        $spec = $service->loadSpec();

        // OpenAPI version must be 3.1.x
        $this->assertStringStartsWith('3.1', (string) ($spec['openapi'] ?? ''), 'OpenAPI version must be 3.1.x');

        // Info section must exist with version 1.4.0
        $this->assertEquals('1.4.0', $spec['info']['version'] ?? '');
        $this->assertStringContainsString('Internship', $spec['info']['title'] ?? '');

        // Servers must include local development server
        $this->assertArrayHasKey('servers', $spec);
        $serverUrls = array_column($spec['servers'] ?? [], 'url');
        $this->assertContains('http://127.0.0.1:8000/api/v1', $serverUrls);

        // Security schemes
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('securitySchemes', $spec['components']);
        $schemes = $spec['components']['securitySchemes'];
        $this->assertArrayHasKey('BearerSanctumAuth', $schemes);
        $this->assertArrayHasKey('PartnerHmacHeaders', $schemes);
        $this->assertEquals('http', $schemes['BearerSanctumAuth']['type']);
        $this->assertEquals('bearer', $schemes['BearerSanctumAuth']['scheme']);
        $this->assertEquals('apiKey', $schemes['PartnerHmacHeaders']['type']);
        $this->assertEquals('X-Signature-256', $schemes['PartnerHmacHeaders']['name']);

        // Required component schemas
        $requiredSchemas = [
            'ApiSuccessEnvelope',
            'ApiErrorEnvelope',
            'ValidationErrorEnvelope',
            'UserSummary',
            'InternshipApplication',
            'ApplicationStatusLog',
            'AutomationJob',
            'SecureIntegrationEnvelope',
            'SecurityAuditEvent',
            'PerformanceBenchmarkRun',
            'ContractAuditReport',
        ];
        foreach ($requiredSchemas as $schema) {
            $this->assertArrayHasKey($schema, $spec['components']['schemas'] ?? [], "Missing schema: {$schema}");
        }

        // Paths must document all major endpoint groups
        $paths = array_keys($spec['paths'] ?? []);
        $expectedPaths = [
            '/health',
            '/health/deep',
            '/auth/login',
            '/auth/me',
            '/auth/logout',
            '/auth/revoke-all',
            '/applications',
            '/applications/{id}',
            '/applications/{id}/transition',
            '/partner-reviewers',
            '/automation/jobs',
            '/automation/sweeps/run',
            '/automation/jobs/{job}/requeue',
            '/automation/summaries/weekly',
            '/integrations/envelopes',
            '/integrations/keyring/status',
            '/integrations/partner-packets/dispatch',
            '/integrations/partner-callbacks/outcome',
            '/integrations/verify-simulation',
            '/security/audit-events',
            '/security/posture',
            '/performance/metrics',
            '/performance/benchmarks/run',
            '/docs/openapi.yaml',
            '/docs/openapi.json',
            '/docs/portal',
            '/docs/contract-audit',
        ];
        foreach ($expectedPaths as $path) {
            $this->assertContains($path, $paths, "Missing path in spec: {$path}");
        }
    }

    public function test_tc_17_02_live_routes_achieve_100_percent_parity(): void
    {
        $service = new OpenApiContractService();
        $parity = $service->auditParity();

        $this->assertTrue(
            $parity['parity_passed'],
            'Route parity failed. Missing from spec: ' . implode(', ', $parity['missing_from_spec']) .
            '. Stale in spec: ' . implode(', ', $parity['stale_in_spec'])
        );

        $this->assertGreaterThan(0, $parity['documented_operations']);
        $this->assertEquals('1.4.0', $parity['contract_version']);
        $this->assertEquals('3.1.0', $parity['openapi_version']);
        $this->assertEquals([], $parity['missing_from_spec']);
        $this->assertEquals([], $parity['stale_in_spec']);
    }

    public function test_tc_17_03_openapi_json_endpoint_returns_etag_and_304(): void
    {
        // First request gets ETag
        $first = $this->getJson('/api/v1/docs/openapi.json')
            ->assertOk()
            ->assertJsonStructure(['openapi', 'info', 'paths', 'components'])
            ->assertHeader('X-API-Contract-Version', '1.4.0')
            ->assertHeader('X-OpenAPI-Spec', '3.1.0')
            ->assertHeader('ETag');

        $etag = $first->headers->get('ETag');
        $this->assertNotNull($etag);

        // Second request with If-None-Match returns 304
        $second = $this->get('/api/v1/docs/openapi.json', [
            'If-None-Match' => $etag,
            'Accept' => 'application/json',
        ]);

        $this->assertEquals(304, $second->getStatusCode());
        $this->assertEquals($etag, $second->headers->get('ETag'));
        $this->assertStringContainsString('max-age=3600', $second->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', $second->headers->get('Cache-Control'));
        $this->assertEquals('1.4.0', $second->headers->get('X-API-Contract-Version'));
    }

    public function test_tc_17_04_portal_returns_self_contained_html(): void
    {
        $response = $this->get('/api/v1/docs/portal')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertHeader('X-API-Contract-Version', '1.4.0')
            ->assertHeader('X-OpenAPI-Spec', '3.1.0')
            ->assertHeader('Content-Security-Policy');

        $content = $response->getContent();
        $this->assertStringContainsString('<title>Internship Monitor API', $content);
        $this->assertStringContainsString('OpenAPI 3.1.0', $content);
        $this->assertStringContainsString('Contract v1.4.0', $content);
        $this->assertStringContainsString('Documented Endpoints', $content);
        $this->assertStringContainsString('search-input', $content);
        $this->assertStringContainsString('tag-filters', $content);
        $this->assertStringContainsString('endpoint-catalog', $content);
        $this->assertStringContainsString('Copy cURL', $content);
        // Verify zero external CDN scripts
        $this->assertStringNotContainsString('cdn.', $content);
        $this->assertStringNotContainsString('unpkg.com', $content);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $content);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $content);
    }

    public function test_tc_17_05_zero_secret_scan_confirms_no_live_keys(): void
    {
        $service = new OpenApiContractService();
        $scan = $service->scanForSecrets();

        $this->assertTrue(
            $scan['clean'],
            'Secret scan violations: ' . implode('; ', $scan['violations'])
        );
    }

    public function test_tc_17_06_simulated_undocumented_route_triggers_drift_detection(): void
    {
        // Register a temporary undocumented route
        Route::get('/api/v1/test-undocumented-drift', function () {
            return response()->json(['test' => true]);
        });

        // Rebuild route collection
        $this->app->router->getRoutes()->refreshNameLookups();
        $this->app->router->getRoutes()->refreshActionLookups();

        $service = new OpenApiContractService();
        $parity = $service->auditParity();

        // Should detect the missing route
        $this->assertFalse($parity['parity_passed'], 'Parity should fail with undocumented route');
        $this->assertNotEmpty($parity['missing_from_spec']);

        // Verify the specific route is detected
        $found = false;
        foreach ($parity['missing_from_spec'] as $missing) {
            if (str_contains($missing, 'test-undocumented-drift')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Undocumented test route should be detected as missing from spec');
    }

    public function test_tc_17_07_non_coordinator_denied_contract_audit(): void
    {
        $student = User::factory()->student()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/docs/contract-audit')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This action is unauthorized.');
    }
}