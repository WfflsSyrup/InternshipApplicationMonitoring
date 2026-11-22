<?php

namespace App\Services\Contract;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

/**
 * Week 17 — Module 17 / SA11: OpenAPI Contract Service.
 *
 * Loads and parses the OpenAPI 3.1 specification, provides parity auditing
 * against live Laravel routes, and scans for secrets in the spec file.
 */
class OpenApiContractService
{
    private ?array $specCache = null;

    public function __construct()
    {
    }

    /**
     * Load and parse the OpenAPI specification file.
     *
     * @return array<string, mixed>
     */
    public function loadSpec(): array
    {
        if ($this->specCache !== null) {
            return $this->specCache;
        }

        $path = config('contract.spec.path', base_path('openapi/project10-v1.openapi.yaml'));

        if (! File::exists($path)) {
            throw new \RuntimeException("OpenAPI specification not found at: {$path}");
        }

        $content = File::get($path);

        try {
            $this->specCache = Yaml::parse($content);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to parse OpenAPI YAML: ' . $e->getMessage(), 0, $e);
        }

        if (! isset($this->specCache['openapi']) || ! str_starts_with((string) $this->specCache['openapi'], '3.1')) {
            throw new \RuntimeException('Specification must be OpenAPI 3.1.x, found: ' . ($this->specCache['openapi'] ?? 'unknown'));
        }

        return $this->specCache;
    }

    /**
     * Get all documented operations from the OpenAPI spec.
     *
     * @return array<string, array{method: string, path: string, operationId: string}>
     */
    public function getDocumentedOperations(): array
    {
        $spec = $this->loadSpec();
        $operations = [];

        if (! isset($spec['paths']) || ! is_array($spec['paths'])) {
            return $operations;
        }

        foreach ($spec['paths'] as $path => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }

            foreach (['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'] as $method) {
                if (! isset($pathItem[$method]) || ! is_array($pathItem[$method])) {
                    continue;
                }

                $operation = $pathItem[$method];
                $operationId = $operation['operationId'] ?? "{$method}_{$path}";

                $operations[$operationId] = [
                    'method' => strtoupper($method),
                    'path' => $this->normalizeSpecPath($path),
                    'operationId' => $operationId,
                ];
            }
        }

        return $operations;
    }

    /**
     * Get all live Laravel routes under /api/v1/*, normalized for comparison.
     *
     * @return array<string, array{method: string, path: string, name: string}>
     */
    public function getLiveRoutes(): array
    {
        $routes = [];
        $excludePatterns = config('contract.parity_audit.exclude_patterns', ['/docs/*']);

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            // Only consider /api/v1/* routes
            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }

            // Check exclude patterns
            $excluded = false;
            foreach ($excludePatterns as $pattern) {
                $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
                if (preg_match($regex, $uri)) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            $methods = $route->methods();
            $httpMethods = array_filter($methods, fn ($m) => $m !== 'HEAD');

            foreach ($httpMethods as $method) {
                $normalizedPath = $this->normalizeLiveRoutePath($uri);
                $routeKey = "{$method}:{$normalizedPath}";

                $routes[$routeKey] = [
                    'method' => strtoupper($method),
                    'path' => $normalizedPath,
                    'name' => $route->getName() ?? '',
                ];
            }
        }

        return $routes;
    }

    /**
     * Audit parity between live routes and documented operations.
     *
     * @return array{parity_passed: bool, contract_version: string, openapi_version: string, documented_operations: int, missing_from_spec: string[], stale_in_spec: string[]}
     */
    public function auditParity(): array
    {
        $documented = $this->getDocumentedOperations();
        $live = $this->getLiveRoutes();

        // Build lookup maps for comparison
        $docMap = [];
        foreach ($documented as $op) {
            $key = "{$op['method']}:{$op['path']}";
            $docMap[$key] = $op['operationId'];
        }

        $liveMap = [];
        foreach ($live as $route) {
            $key = "{$route['method']}:{$route['path']}";
            $liveMap[$key] = $route['name'];
        }

        // Find missing from spec (live routes not documented)
        $missingFromSpec = [];
        foreach ($liveMap as $key => $name) {
            if (! isset($docMap[$key])) {
                $missingFromSpec[] = $key . ($name ? " (route: {$name})" : '');
            }
        }

        // Find stale in spec (documented operations not in live routes)
        $staleInSpec = [];
        foreach ($docMap as $key => $opId) {
            if (! isset($liveMap[$key])) {
                $staleInSpec[] = $key . " (op: {$opId})";
            }
        }

        $parityPassed = empty($missingFromSpec) && empty($staleInSpec);

        return [
            'parity_passed' => $parityPassed,
            'contract_version' => config('contract.spec.version', '1.4.0'),
            'openapi_version' => config('contract.spec.openapi_version', '3.1.0'),
            'documented_operations' => count($documented),
            'missing_from_spec' => array_values($missingFromSpec),
            'stale_in_spec' => array_values($staleInSpec),
        ];
    }

    /**
     * Scan the OpenAPI specification for secrets, live keys, passwords, or real PII.
     *
     * @return array{clean: bool, violations: string[]}
     */
    public function scanForSecrets(): array
    {
        $path = config('contract.spec.path', base_path('openapi/project10-v1.openapi.yaml'));
        $content = File::get($path);

        $patterns = config('contract.secret_scan.patterns', []);
        $allowedDomains = config('contract.secret_scan.allowed_domains', []);

        $violations = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $violation = $match[0];
                    $context = $this->getContext($content, $match[1]);

                    // Check if it's a false positive (contains allowed domain)
                    $isAllowed = false;
                    foreach ($allowedDomains as $domain) {
                        if (str_contains($violation, $domain)) {
                            $isAllowed = true;
                            break;
                        }
                    }

                    // Check if it's clearly a placeholder/example
                    $isPlaceholder = $this->isPlaceholder($violation);

                    if (! $isAllowed && ! $isPlaceholder) {
                        $violations[] = "Pattern matched: {$violation} (context: {$context})";
                    }
                }
            }
        }

        return [
            'clean' => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Normalize OpenAPI spec path (convert {param} to {param} format).
     */
    private function normalizeSpecPath(string $path): string
    {
        // OpenAPI uses {param} format, which is already normalized
        return $path;
    }

    /**
     * Normalize Laravel route path (convert {param} to {param} and handle optional params).
     */
    private function normalizeLiveRoutePath(string $uri): string
    {
        // Remove the leading 'api/v1' prefix since spec paths start with '/'
        $path = '/' . ltrim(str_replace('api/v1/', '', $uri), '/');

        // Convert Laravel {param?} to {param} (optional params still documented)
        $path = preg_replace('/\{(\w+)\?\}/', '{$1}', $path);

        // Normalize parameter names to match OpenAPI spec convention
        // Laravel uses {application}, {job}, etc. but spec uses {id}, {job}
        $path = preg_replace('/\{application\}/', '{id}', $path);
        $path = preg_replace('/\{job\}/', '{job}', $path);

        return $path;
    }

    /**
     * Get context around a match for reporting.
     */
    private function getContext(string $content, int $offset): string
    {
        $start = max(0, $offset - 50);
        $length = min(100, strlen($content) - $start);
        $snippet = substr($content, $start, $length);
        return trim(str_replace("\n", ' ', $snippet));
    }

    /**
     * Check if a matched string is clearly a placeholder/example.
     */
    private function isPlaceholder(string $value): bool
    {
        $placeholderPatterns = [
            '/replace-with-/i',
            '/placeholder/i',
            '/example\.com/i',
            '/test\.com/i',
            '/demo\./i',
            '/your-.*-key/i',
            '/your-.*-secret/i',
            '/xxx+/',
            '/\*+/',
            '/<.*>/',
        ];

        foreach ($placeholderPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        // Check for obvious test/fake values
        $lower = strtolower($value);
        $fakePatterns = [
            'fake',
            'test',
            'demo',
            'sample',
            'dummy',
            'placeholder',
            'example',
            'uls-test',
        ];

        foreach ($fakePatterns as $fake) {
            if (str_contains($lower, $fake)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the full spec as array for rendering.
     *
     * @return array<string, mixed>
     */
    public function getSpec(): array
    {
        return $this->loadSpec();
    }

    /**
     * Generate ETag for the spec content.
     */
    public function getSpecETag(): string
    {
        $path = config('contract.spec.path', base_path('openapi/project10-v1.openapi.yaml'));
        $content = File::get($path);
        return '"' . hash('sha256', $content) . '"';
    }
}