class SecurityPostureModel {
  const SecurityPostureModel({
    required this.strictCors,
    required this.securityHeadersEnabled,
    required this.maxPayloadKb,
    required this.sanctumTtlMinutes,
    required this.loginRateLimitPerMin,
    required this.apiRateLimitPerMin,
    required this.webhookRateLimitPerMin,
    required this.debugModeDisabled,
    required this.inputSanitizationEnabled,
    required this.auditEventsTotal,
    required this.auditEventsLast24h,
    this.securityHeaders = const <String>[],
  });

  factory SecurityPostureModel.fromJson(Map<String, dynamic> json) {
    return SecurityPostureModel(
      strictCors: json['strict_cors'] == true,
      securityHeadersEnabled: json['security_headers_enabled'] == true,
      maxPayloadKb: json['max_payload_kb'] as int? ?? 0,
      sanctumTtlMinutes: json['sanctum_ttl_minutes'] as int? ?? 0,
      loginRateLimitPerMin: json['login_rate_limit_per_min'] as int? ?? 0,
      apiRateLimitPerMin: json['api_rate_limit_per_min'] as int? ?? 0,
      webhookRateLimitPerMin: json['webhook_rate_limit_per_min'] as int? ?? 0,
      debugModeDisabled: json['debug_mode_disabled'] == true,
      inputSanitizationEnabled: json['input_sanitization_enabled'] == true,
      auditEventsTotal: json['audit_events_total'] as int? ?? 0,
      auditEventsLast24h: json['audit_events_last_24h'] as int? ?? 0,
      securityHeaders: (json['security_headers'] as List<dynamic>? ?? const [])
          .map((dynamic value) => value.toString())
          .toList(growable: false),
    );
  }

  final bool strictCors;
  final bool securityHeadersEnabled;
  final int maxPayloadKb;
  final int sanctumTtlMinutes;
  final int loginRateLimitPerMin;
  final int apiRateLimitPerMin;
  final int webhookRateLimitPerMin;
  final bool debugModeDisabled;
  final bool inputSanitizationEnabled;
  final int auditEventsTotal;
  final int auditEventsLast24h;
  final List<String> securityHeaders;

  List<SecurityCheck> get checks => <SecurityCheck>[
    SecurityCheck(
      label: 'Strict CORS allowlist',
      detail: 'Explicit origin allowlist — no wildcard origins.',
      passed: strictCors,
    ),
    SecurityCheck(
      label: 'HTTP security headers',
      detail:
          '${securityHeaders.length} hardening headers attached to every API response.',
      passed: securityHeadersEnabled,
    ),
    SecurityCheck(
      label: '$maxPayloadKb KB payload cap',
      detail: 'Oversized request bodies are rejected with 413 before controllers.',
      passed: maxPayloadKb > 0 && maxPayloadKb <= 256,
    ),
    SecurityCheck(
      label: '$sanctumTtlMinutes-min token TTL',
      detail: 'Personal access tokens expire after the configured lifetime.',
      passed: sanctumTtlMinutes > 0 && sanctumTtlMinutes <= 120,
    ),
    SecurityCheck(
      label: 'Tiered rate limiting',
      detail:
          'Login $loginRateLimitPerMin/min · API $apiRateLimitPerMin/min · webhook $webhookRateLimitPerMin/min.',
      passed: loginRateLimitPerMin > 0 && apiRateLimitPerMin > 0,
    ),
    SecurityCheck(
      label: 'Debug mode disabled',
      detail: 'Stack traces and environment details are not leaked.',
      passed: debugModeDisabled,
    ),
  ];

  int get passedChecks => checks.where((SecurityCheck check) => check.passed).length;
}

class SecurityCheck {
  const SecurityCheck({
    required this.label,
    required this.detail,
    required this.passed,
  });

  final bool passed;
  final String label;
  final String detail;
}