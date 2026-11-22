class ApiContractReport {
  final bool parityPassed;
  final String contractVersion;
  final String openapiVersion;
  final int documentedOperations;
  final List<String> missingFromSpec;
  final List<String> staleInSpec;
  final bool secretScanClean;

  const ApiContractReport({
    required this.parityPassed,
    required this.contractVersion,
    required this.openapiVersion,
    required this.documentedOperations,
    required this.missingFromSpec,
    required this.staleInSpec,
    required this.secretScanClean,
  });

  factory ApiContractReport.fromJson(Map<String, dynamic> json) {
    return ApiContractReport(
      parityPassed: json['parity_passed'] as bool? ?? false,
      contractVersion: json['contract_version'] as String? ?? '',
      openapiVersion: json['openapi_version'] as String? ?? '',
      documentedOperations: json['documented_operations'] as int? ?? 0,
      missingFromSpec: (json['missing_from_spec'] as List<dynamic>? ?? [])
          .map((e) => e as String)
          .toList(),
      staleInSpec: (json['stale_in_spec'] as List<dynamic>? ?? [])
          .map((e) => e as String)
          .toList(),
      secretScanClean: json['secret_scan_clean'] as bool? ?? false,
    );
  }
}

class ApiEndpointSummary {
  final String method;
  final String path;
  final String summary;
  final String operationId;
  final String tag;
  final List<String> security;
  final Map<String, dynamic> responses;
  final List<dynamic> parameters;
  final dynamic requestBody;

  const ApiEndpointSummary({
    required this.method,
    required this.path,
    required this.summary,
    required this.operationId,
    required this.tag,
    required this.security,
    required this.responses,
    required this.parameters,
    required this.requestBody,
  });

  factory ApiEndpointSummary.fromJson(Map<String, dynamic> json) {
    return ApiEndpointSummary(
      method: json['method'] as String? ?? '',
      path: json['path'] as String? ?? '',
      summary: json['summary'] as String? ?? '',
      operationId: json['operationId'] as String? ?? '',
      tag: json['tag'] as String? ?? '',
      security: (json['security'] as List<dynamic>? ?? [])
          .map((e) => e as String)
          .toList(),
      responses: json['responses'] as Map<String, dynamic>? ?? {},
      parameters: json['parameters'] as List<dynamic>? ?? [],
      requestBody: json['requestBody'],
    );
  }
}

class OpenApiSpecSummary {
  final String openapiVersion;
  final String infoVersion;
  final String infoTitle;
  final String baseUrl;
  final int documentedEndpoints;
  final List<String> tags;

  const OpenApiSpecSummary({
    required this.openapiVersion,
    required this.infoVersion,
    required this.infoTitle,
    required this.baseUrl,
    required this.documentedEndpoints,
    required this.tags,
  });

  factory OpenApiSpecSummary.fromJson(Map<String, dynamic> json) {
    final info = json['info'] as Map<String, dynamic>? ?? {};
    final servers = json['servers'] as List<dynamic>? ?? [];
    final paths = json['paths'] as Map<String, dynamic>? ?? {};

    int endpointCount = 0;
    paths.forEach((_, pathItem) {
      if (pathItem is Map) {
        for (final method in ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace']) {
          if (pathItem[method] != null) endpointCount++;
        }
      }
    });

    return OpenApiSpecSummary(
      openapiVersion: json['openapi'] as String? ?? '',
      infoVersion: info['version'] as String? ?? '',
      infoTitle: info['title'] as String? ?? '',
      baseUrl: servers.isNotEmpty && servers[0] is Map
          ? (servers[0] as Map)['url'] as String? ?? ''
          : '',
      documentedEndpoints: endpointCount,
      tags: (json['tags'] as List<dynamic>? ?? [])
          .map((e) => (e as Map)['name'] as String? ?? '')
          .where((e) => e.isNotEmpty)
          .toList(),
    );
  }
}