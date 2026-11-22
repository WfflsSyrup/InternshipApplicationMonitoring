<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Contract\OpenApiContractService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\Yaml\Yaml;

class ApiDocsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OpenApiContractService $contractService,
    ) {
    }

    /**
     * GET /api/v1/docs/openapi.yaml
     * Returns the raw OpenAPI 3.1 YAML specification with ETag caching.
     */
    public function openApiYaml(Request $request): Response
    {
        $path = config('contract.spec.path', base_path('openapi/project10-v1.openapi.yaml'));
        $content = file_get_contents($path);

        if ($content === false) {
            return $this->error('OpenAPI specification not found', 404);
        }

        $etag = $this->contractService->getSpecETag();

        // Check If-None-Match for 304
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null && $ifNoneMatch === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=3600, must-revalidate')
                ->header('X-API-Contract-Version', config('contract.spec.version', '1.4.0'))
                ->header('X-OpenAPI-Spec', config('contract.spec.openapi_version', '3.1.0'));
        }

        return response($content, 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=3600, must-revalidate',
            'X-API-Contract-Version' => config('contract.spec.version', '1.4.0'),
            'X-OpenAPI-Spec' => config('contract.spec.openapi_version', '3.1.0'),
        ]);
    }

    /**
     * GET /api/v1/docs/openapi.json
     * Returns the parsed OpenAPI 3.1 specification as JSON with ETag caching.
     */
    public function openApiJson(Request $request): JsonResponse
    {
        $spec = $this->contractService->getSpec();
        $etag = $this->contractService->getSpecETag();

        // Check If-None-Match for 304
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null && $ifNoneMatch === $etag) {
            return response()->json([], 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=3600, must-revalidate')
                ->header('X-API-Contract-Version', config('contract.spec.version', '1.4.0'))
                ->header('X-OpenAPI-Spec', config('contract.spec.openapi_version', '3.1.0'));
        }

        return response()->json($spec, 200, [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=3600, must-revalidate',
            'X-API-Contract-Version' => config('contract.spec.version', '1.4.0'),
            'X-OpenAPI-Spec' => config('contract.spec.openapi_version', '3.1.0'),
        ]);
    }

    /**
     * GET /api/v1/docs/portal
     * Renders a self-contained HTML5 API documentation explorer.
     */
    public function portal(): Response
    {
        $spec = $this->contractService->getSpec();
        $csp = config('contract.portal.csp', "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'");

        $html = $this->renderPortalHtml($spec);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => $csp,
            'X-API-Contract-Version' => config('contract.spec.version', '1.4.0'),
            'X-OpenAPI-Spec' => config('contract.spec.openapi_version', '3.1.0'),
        ]);
    }

    /**
     * GET /api/v1/docs/contract-audit
     * Returns live contract parity audit report (coordinator only).
     */
    public function contractAudit(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\InternshipApplication::class);

        // Ensure coordinator only
        abort_unless(request()->user()?->isCoordinator() === true, 403, 'This action is unauthorized.');

        $parity = $this->contractService->auditParity();
        $secretScan = $this->contractService->scanForSecrets();

        $report = array_merge($parity, [
            'secret_scan_clean' => $secretScan['clean'],
            'secret_violations' => $secretScan['violations'],
        ]);

        return self::success($report, 'Contract audit completed successfully.');
    }

    /**
     * Render the self-contained HTML portal.
     */
    private function renderPortalHtml(array $spec): string
    {
        $title = config('contract.portal.title', 'Internship Monitor API — Developer Portal');
        $description = config('contract.portal.description', 'Self-contained API documentation explorer generated from OpenAPI 3.1 spec');
        $contractVersion = config('contract.spec.version', '1.4.0');
        $openApiVersion = config('contract.spec.openapi_version', '3.1.0');
        $baseUrl = config('app.url', 'http://127.0.0.1:8000') . '/api/v1';

        // Extract paths and operations for the catalog
        $paths = $spec['paths'] ?? [];
        $tags = $spec['tags'] ?? [];

        $tagMap = [];
        foreach ($tags as $tag) {
            $tagMap[$tag['name']] = $tag['description'] ?? '';
        }

        // Build endpoint catalog
        $endpoints = [];
        foreach ($paths as $path => $pathItem) {
            foreach (['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'] as $method) {
                if (! isset($pathItem[$method]) || ! is_array($pathItem[$method])) {
                    continue;
                }
                $op = $pathItem[$method];
                $tag = $op['tags'][0] ?? 'Other';
                $endpoints[] = [
                    'method' => strtoupper($method),
                    'path' => $path,
                    'summary' => $op['summary'] ?? '',
                    'description' => $op['description'] ?? '',
                    'operationId' => $op['operationId'] ?? '',
                    'tag' => $tag,
                    'security' => $op['security'] ?? [],
                    'responses' => $op['responses'] ?? [],
                    'parameters' => $op['parameters'] ?? [],
                    'requestBody' => $op['requestBody'] ?? null,
                ];
            }
        }

        // Sort by tag then method then path
        usort($endpoints, function ($a, $b) {
            $tagCmp = strcmp($a['tag'], $b['tag']);
            if ($tagCmp !== 0) return $tagCmp;
            $methodOrder = ['GET' => 0, 'POST' => 1, 'PUT' => 2, 'PATCH' => 3, 'DELETE' => 4];
            $methodCmp = ($methodOrder[$a['method']] ?? 99) - ($methodOrder[$b['method']] ?? 99);
            if ($methodCmp !== 0) return $methodCmp;
            return strcmp($a['path'], $b['path']);
        });

        // Group by tag
        $endpointsByTag = [];
        foreach ($endpoints as $ep) {
            $endpointsByTag[$ep['tag']][] = $ep;
        }

        $specJson = json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $endpointsJson = json_encode($endpoints, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $tagsJson = json_encode(array_keys($endpointsByTag), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $endpointsByTagJson = json_encode($endpointsByTag, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; line-height: 1.6; color: #1a1a2e; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        header { margin-bottom: 32px; padding-bottom: 24px; border-bottom: 2px solid #e0e0e0; }
        h1 { font-size: 2rem; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .subtitle { color: #64748b; font-size: 1.1rem; }
        .badges { display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap; }
        .badge { display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; background: #e2e8f0; color: #334155; }
        .badge.primary { background: #3b82f6; color: white; }
        .badge.success { background: #10b981; color: white; }
        .badge.warning { background: #f59e0b; color: white; }
        .badge.danger { background: #ef4444; color: white; }
        .search-bar { margin: 24px 0; position: relative; }
        .search-bar input { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; }
        .search-bar input:focus { outline: none; border-color: #3b82f6; }
        .tag-filters { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .tag-filter { padding: 8px 16px; border: 2px solid #e2e8f0; background: white; border-radius: 20px; cursor: pointer; font-size: 0.85rem; font-weight: 500; color: #475569; transition: all 0.2s; }
        .tag-filter:hover { border-color: #3b82f6; color: #3b82f6; }
        .tag-filter.active { background: #3b82f6; border-color: #3b82f6; color: white; }
        .endpoint-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; overflow: hidden; transition: box-shadow 0.2s; }
        .endpoint-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .endpoint-header { display: flex; align-items: center; gap: 16px; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; background: #fafbfc; }
        .method-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 80px; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; color: white; text-transform: uppercase; letter-spacing: 0.5px; }
        .method-get { background: #10b981; }
        .method-post { background: #3b82f6; }
        .method-put { background: #f59e0b; }
        .method-patch { background: #8b5cf6; }
        .method-delete { background: #ef4444; }
        .endpoint-path { font-family: 'Monaco', 'Consolas', monospace; font-size: 0.9rem; color: #0f172a; font-weight: 500; flex: 1; }
        .endpoint-tag { font-size: 0.7rem; font-weight: 600; padding: 4px 10px; border-radius: 12px; background: #e2e8f0; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
        .endpoint-body { padding: 20px; }
        .endpoint-summary { color: #475569; margin-bottom: 12px; }
        .endpoint-description { color: #64748b; font-size: 0.9rem; margin-bottom: 16px; white-space: pre-wrap; }
        .status-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
        .status-chip { font-size: 0.7rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; background: #f1f5f9; color: #334155; }
        .status-chip.success { background: #dcfce7; color: #166534; }
        .status-chip.redirect { background: #dbeafe; color: #1e40af; }
        .status-chip.client-error { background: #fee2e2; color: #991b1b; }
        .status-chip.server-error { background: #fecaca; color: #7f1d1d; }
        .auth-badges { display: flex; gap: 8px; margin-bottom: 16px; }
        .auth-badge { font-size: 0.7rem; font-weight: 500; padding: 4px 10px; border-radius: 4px; background: #fef3c7; color: #92400e; }
        .curl-section { background: #0f172a; border-radius: 8px; padding: 16px; margin-top: 16px; position: relative; }
        .curl-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .curl-title { font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .copy-btn { background: #1e293b; border: 1px solid #334155; color: #e2e8f0; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 500; transition: all 0.2s; }
        .copy-btn:hover { background: #334155; }
        .copy-btn.copied { background: #10b981; border-color: #10b981; color: white; }
        .curl-code { font-family: 'Monaco', 'Consolas', monospace; font-size: 0.8rem; color: #e2e8f0; white-space: pre-wrap; word-break: break-word; line-height: 1.5; }
        .curl-code .token { color: #38bdf8; }
        .curl-code .string { color: #fde68a; }
        .curl-code .comment { color: #64748b; }
        .endpoint-details { display: none; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .endpoint-details.open { display: block; animation: slideDown 0.2s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .detail-section { margin-bottom: 16px; }
        .detail-title { font-size: 0.8rem; font-weight: 600; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .param-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .param-table th, .param-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .param-table th { background: #f8fafc; font-weight: 600; color: #475569; }
        .param-table code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        .empty-state { text-align: center; padding: 48px 24px; color: #94a3b8; }
        .empty-state svg { width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.5; }
        .footer { margin-top: 48px; padding-top: 24px; border-top: 1px solid #e2e8f0; text-align: center; color: #94a3b8; font-size: 0.85rem; }
        .hidden { display: none !important; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>{$title}</h1>
            <p class="subtitle">{$description}</p>
            <div class="badges">
                <span class="badge primary">OpenAPI {$openApiVersion}</span>
                <span class="badge">Contract v{$contractVersion}</span>
                <span class="badge success">Base URL: {$baseUrl}</span>
                <span class="badge warning">Documented Endpoints: <span id="endpoint-count">0</span></span>
            </div>
        </header>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Search endpoints by path, summary, or operation ID..." autocomplete="off">
        </div>

        <div class="tag-filters" id="tag-filters">
            <button class="tag-filter active" data-tag="all">All Tags</button>
        </div>

        <div id="endpoint-catalog"></div>

        <footer class="footer">
            <p>Generated from OpenAPI 3.1.0 specification • PFEBSIT 002 — University of La Salette, Inc.</p>
            <p>All examples use fictional <code>*.uls-test.edu.ph</code> domains and placeholder tokens</p>
        </footer>
    </div>

    <script>
        // Embedded spec data
        const SPEC = {$specJson};
        const ENDPOINTS = {$endpointsJson};
        const TAGS = {$tagsJson};
        const ENDPOINTS_BY_TAG = {$endpointsByTagJson};

        // DOM elements
        const searchInput = document.getElementById('search-input');
        const tagFilters = document.getElementById('tag-filters');
        const catalog = document.getElementById('endpoint-catalog');
        const endpointCount = document.getElementById('endpoint-count');

        let currentTag = 'all';
        let currentSearch = '';

        // Initialize tag filters
        function initTagFilters() {
            TAGS.forEach(tag => {
                const btn = document.createElement('button');
                btn.className = 'tag-filter';
                btn.dataset.tag = tag;
                btn.textContent = tag;
                btn.addEventListener('click', () => setTagFilter(tag));
                tagFilters.appendChild(btn);
            });
            updateEndpointCount();
        }

        // Set active tag filter
        function setTagFilter(tag) {
            currentTag = tag;
            document.querySelectorAll('.tag-filter').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tag === tag);
            });
            renderCatalog();
        }

        // Search handler
        searchInput.addEventListener('input', (e) => {
            currentSearch = e.target.value.toLowerCase().trim();
            renderCatalog();
        });

        // Render endpoint catalog
        function renderCatalog() {
            const filtered = ENDPOINTS.filter(ep => {
                const matchesTag = currentTag === 'all' || ep.tag === currentTag;
                const matchesSearch = !currentSearch ||
                    ep.path.toLowerCase().includes(currentSearch) ||
                    ep.summary.toLowerCase().includes(currentSearch) ||
                    ep.operationId.toLowerCase().includes(currentSearch) ||
                    ep.method.toLowerCase().includes(currentSearch);
                return matchesTag && matchesSearch;
            });

            if (filtered.length === 0) {
                catalog.innerHTML = \`
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.35-4.35"/>
                        </svg>
                        <p>No endpoints match your filters</p>
                    </div>
                \`;
                endpointCount.textContent = '0';
                return;
            }

            endpointCount.textContent = filtered.length;

            catalog.innerHTML = filtered.map(ep => createEndpointCard(ep)).join('');

            // Attach event listeners
            document.querySelectorAll('.endpoint-header').forEach(header => {
                header.addEventListener('click', () => {
                    const card = header.closest('.endpoint-card');
                    const details = card.querySelector('.endpoint-details');
                    details.classList.toggle('open');
                });
            });

            document.querySelectorAll('.copy-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const code = btn.dataset.curl;
                    navigator.clipboard.writeText(code).then(() => {
                        btn.textContent = 'Copied!';
                        btn.classList.add('copied');
                        setTimeout(() => {
                            btn.textContent = 'Copy cURL';
                            btn.classList.remove('copied');
                        }, 2000);
                    });
                });
            });
        }

        // Create endpoint card HTML
        function createEndpointCard(ep) {
            const methodClass = 'method-' + ep.method.toLowerCase();
            const statusChips = createStatusChips(ep.responses);
            const authBadges = createAuthBadges(ep.security);
            const curlExample = generateCurlExample(ep);

            return \`
                <div class="endpoint-card">
                    <div class="endpoint-header">
                        <span class="method-badge \${methodClass}">\${ep.method}</span>
                        <span class="endpoint-path">\${ep.path}</span>
                        <span class="endpoint-tag">\${ep.tag}</span>
                    </div>
                    <div class="endpoint-body">
                        <p class="endpoint-summary">\${escapeHtml(ep.summary)}</p>
                        \${ep.description ? \`<p class="endpoint-description">\${escapeHtml(ep.description)}</p>\` : ''}
                        <div class="status-chips">\${statusChips}</div>
                        \${authBadges ? \`<div class="auth-badges">\${authBadges}</div>\` : ''}
                        <div class="curl-section">
                            <div class="curl-header">
                                <span class="curl-title">cURL Example (with placeholder tokens)</span>
                                <button class="copy-btn" data-curl=\${escapeHtml(JSON.stringify(curlExample))}>Copy cURL</button>
                            </div>
                            <pre class="curl-code">\${escapeHtml(curlExample)}</pre>
                        </div>
                        <div class="endpoint-details">
                            \${createDetailsSection(ep)}
                        </div>
                    </div>
                </div>
            \`;
        }

        // Create status code chips
        function createStatusChips(responses) {
            const statusOrder = ['200', '201', '204', '304', '400', '401', '403', '404', '409', '413', '422', '429', '500', '503'];
            let chips = '';
            statusOrder.forEach(code => {
                if (responses[code]) {
                    const desc = responses[code].description || '';
                    let cls = 'status-chip';
                    if (code.startsWith('2')) cls += ' success';
                    else if (code.startsWith('3')) cls += ' redirect';
                    else if (code.startsWith('4')) cls += ' client-error';
                    else if (code.startsWith('5')) cls += ' server-error';
                    chips += \`<span class="\${cls}">\${code}</span>\`;
                }
            });
            return chips;
        }

        // Create auth badges
        function createAuthBadges(security) {
            if (!security || security.length === 0) return '';
            const schemes = new Set();
            security.forEach(s => Object.keys(s).forEach(k => schemes.add(k)));
            let badges = '';
            schemes.forEach(scheme => {
                let label = scheme;
                if (scheme === 'BearerSanctumAuth') label = 'Bearer Token (Sanctum)';
                else if (scheme === 'PartnerHmacHeaders') label = 'HMAC Headers (Partner)';
                badges += \`<span class="auth-badge">\${label}</span>\`;
            });
            return badges;
        }

        // Generate cURL example
        function generateCurlExample(ep) {
            const baseUrl = '{$baseUrl}';
            const url = baseUrl + ep.path.replace(/\{(\w+)\}/g, '{\$1}');
            let curl = \`curl -X \${ep.method} "\${url}"\`;

            // Add headers
            const headers = [];
            const security = ep.security || [];

            if (security.some(s => s.BearerSanctumAuth)) {
                headers.push('-H "Authorization: Bearer replace-with-sanctum-token"');
            }
            if (security.some(s => s.PartnerHmacHeaders)) {
                headers.push('-H "X-Key-Id: replace-with-key-id"');
                headers.push('-H "X-Timestamp: 2026-11-22T09:30:00Z"');
                headers.push('-H "X-Nonce: replace-with-base64-nonce"');
                headers.push('-H "X-Signature-256: sha256=replace-with-hmac-signature"');
            }
            headers.push('-H "Accept: application/json"');

            if (ep.requestBody) {
                headers.push('-H "Content-Type: application/json"');
                const example = getRequestBodyExample(ep.requestBody);
                if (example) {
                    headers.push(\`-d '\${JSON.stringify(example, null, 2)}'\`);
                }
            }

            if (headers.length > 0) {
                curl += ' \\\n  ' + headers.join(' \\\n  ');
            }

            return curl;
        }

        // Get request body example
        function getRequestBodyExample(requestBody) {
            if (!requestBody || !requestBody.content || !requestBody.content['application/json']) return null;
            const schema = requestBody.content['application/json'].schema;
            if (!schema) return null;

            // Return example from schema if available
            if (schema.example) return schema.example;

            // Generate basic example from properties
            if (schema.properties) {
                const example = {};
                Object.entries(schema.properties).forEach(([key, prop]) => {
                    if (prop.example !== undefined) {
                        example[key] = prop.example;
                    } else if (prop.type === 'string') {
                        example[key] = prop.format === 'email' ? 'user@uls-test.edu.ph' :
                                     prop.format === 'date' ? '2026-02-02' :
                                     prop.format === 'date-time' ? '2026-11-22T09:30:00Z' :
                                     'example-string';
                    } else if (prop.type === 'integer') {
                        example[key] = prop.minimum ?? 1;
                    } else if (prop.type === 'boolean') {
                        example[key] = true;
                    } else if (prop.type === 'object') {
                        example[key] = {};
                    }
                });
                return example;
            }
            return null;
        }

        // Create details section (parameters, request body, responses)
        function createDetailsSection(ep) {
            let html = '';

            // Parameters
            if (ep.parameters && ep.parameters.length > 0) {
                html += \`
                    <div class="detail-section">
                        <div class="detail-title">Parameters</div>
                        <table class="param-table">
                            <thead>
                                <tr><th>Name</th><th>In</th><th>Required</th><th>Type</th><th>Description</th></tr>
                            </thead>
                            <tbody>
                \`;
                ep.parameters.forEach(p => {
                    const schema = p.schema || {};
                    html += \`
                        <tr>
                            <td><code>\${escapeHtml(p.name)}</code></td>
                            <td>\${escapeHtml(p.in)}</td>
                            <td>\${p.required ? 'Yes' : 'No'}</td>
                            <td>\${escapeHtml(schema.type || 'object')}\${schema.format ? \` (\${schema.format})\` : ''}</td>
                            <td>\${escapeHtml(p.description || '')}</td>
                        </tr>
                    \`;
                });
                html += '</tbody></table></div>';
            }

            // Request Body
            if (ep.requestBody) {
                html += \`
                    <div class="detail-section">
                        <div class="detail-title">Request Body</div>
                        <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">
                            Content-Type: <code>application/json</code>
                        </p>
                        <pre class="curl-code">\${escapeHtml(JSON.stringify(getRequestBodyExample(ep.requestBody) || {}, null, 2))}</pre>
                    </div>
                \`;
            }

            // Responses
            if (ep.responses && Object.keys(ep.responses).length > 0) {
                html += \`
                    <div class="detail-section">
                        <div class="detail-title">Responses</div>
                        <table class="param-table">
                            <thead>
                                <tr><th>Status</th><th>Description</th></tr>
                            </thead>
                            <tbody>
                \`;
                Object.entries(ep.responses).forEach(([code, resp]) => {
                    let cls = 'status-chip';
                    if (code.startsWith('2')) cls += ' success';
                    else if (code.startsWith('3')) cls += ' redirect';
                    else if (code.startsWith('4')) cls += ' client-error';
                    else if (code.startsWith('5')) cls += ' server-error';
                    html += \`
                        <tr>
                            <td><span class="\${cls}">\${code}</span></td>
                            <td>\${escapeHtml(resp.description || '')}</td>
                        </tr>
                    \`;
                });
                html += '</tbody></table></div>';
            }

            return html;
        }

        // Escape HTML
        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            return String(text)
                .replace(/&/g, '&')
                .replace(/</g, '<')
                .replace(/>/g, '>')
                .replace(/"/g, '"')
                .replace(/'/g, '&#039;');
        }

        // Initialize
        initTagFilters();
        renderCatalog();
    </script>
</body>
</html>
HTML;
    }
}