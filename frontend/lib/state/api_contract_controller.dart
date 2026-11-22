import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../../services/api_contract_service.dart';
import '../../core/network/api_exception.dart';
import '../../models/api_contract_models.dart';

class ApiContractController extends ChangeNotifier {
  final ApiContractService _service;

  ApiContractController({required this._service});

  OpenApiSpecSummary? _specSummary;
  List<ApiEndpointSummary> _endpoints = [];
  List<String> _tags = [];
  String? _selectedTag;
  String _searchQuery = '';
  bool _isLoading = false;
  ApiException? _error;
  ApiContractReport? _contractReport;
  bool _isAuditLoading = false;
  String? _auditError;

  OpenApiSpecSummary? get specSummary => _specSummary;
  List<ApiEndpointSummary> get endpoints => _endpoints;
  List<String> get tags => _tags;
  String? get selectedTag => _selectedTag;
  String get searchQuery => _searchQuery;
  bool get isLoading => _isLoading;
  ApiException? get error => _error;
  ApiContractReport? get contractReport => _contractReport;
  bool get isAuditLoading => _isAuditLoading;
  String? get auditError => _auditError;

  List<ApiEndpointSummary> get filteredEndpoints {
    return _endpoints.where((endpoint) {
      final matchesTag = _selectedTag == null || _selectedTag == 'all' || endpoint.tag == _selectedTag;
      final query = _searchQuery.toLowerCase().trim();
      final matchesSearch = query.isEmpty ||
          endpoint.path.toLowerCase().contains(query) ||
          endpoint.summary.toLowerCase().contains(query) ||
          endpoint.operationId.toLowerCase().contains(query) ||
          endpoint.method.toLowerCase().contains(query);
      return matchesTag && matchesSearch;
    }).toList();
  }

  Future<void> load() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final spec = await _service.fetchOpenApiSpec();
      _specSummary = OpenApiSpecSummary.fromJson(spec);
      _tags = ['all', ..._specSummary!.tags];

      // Parse endpoints from spec
      final paths = spec['paths'] as Map<String, dynamic>? ?? {};
      _endpoints = [];
      paths.forEach((path, pathItem) {
        if (pathItem is Map) {
          for (final method in ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace']) {
            if (pathItem[method] != null) {
              final op = pathItem[method] as Map<String, dynamic>;
              final tag = (op['tags'] as List<dynamic>? ?? []).firstOrNull as String? ?? 'Other';
              _endpoints.add(ApiEndpointSummary(
                method: method.toUpperCase(),
                path: path,
                summary: op['summary'] as String? ?? '',
                operationId: op['operationId'] as String? ?? '',
                tag: tag,
                security: (op['security'] as List<dynamic>? ?? [])
                    .map((s) => (s as Map).keys.first as String)
                    .toList(),
                responses: op['responses'] as Map<String, dynamic>? ?? {},
                parameters: op['parameters'] as List<dynamic>? ?? [],
                requestBody: op['requestBody'],
              ));
            }
          }
        }
      });

      // Sort by tag, then method order, then path
      const methodOrder = {'GET': 0, 'POST': 1, 'PUT': 2, 'PATCH': 3, 'DELETE': 4};
      _endpoints.sort((a, b) {
        final tagCmp = a.tag.compareTo(b.tag);
        if (tagCmp != 0) return tagCmp;
        final methodCmp = (methodOrder[a.method] ?? 99) - (methodOrder[b.method] ?? 99);
        if (methodCmp != 0) return methodCmp;
        return a.path.compareTo(b.path);
      });

      _error = null;
    } on ApiException catch (e) {
      _error = e;
    } catch (e) {
      _error = ApiException(
        message: 'Failed to load API contract: $e',
        kind: ApiErrorKind.unknown,
      );
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void setSearchQuery(String query) {
    _searchQuery = query;
    notifyListeners();
  }

  void setSelectedTag(String? tag) {
    _selectedTag = tag;
    notifyListeners();
  }

  Future<void> runContractAudit() async {
    _isAuditLoading = true;
    _auditError = null;
    notifyListeners();

    try {
      _contractReport = await _service.fetchContractAudit();
      _auditError = null;
    } catch (e) {
      _auditError = 'Failed to run contract audit: $e';
      _contractReport = null;
    } finally {
      _isAuditLoading = false;
      notifyListeners();
    }
  }

  String generateCurlExample(ApiEndpointSummary endpoint) {
    const baseUrl = 'http://127.0.0.1:8000/api/v1';
    final url = baseUrl + endpoint.path.replaceAll(RegExp(r'\{(\w+)\}'), r'{$1}');

    final buffer = StringBuffer();
    buffer.write('curl -X ${endpoint.method} "$url"');

    final headers = <String>[];
    final security = endpoint.security;

    if (security.contains('BearerSanctumAuth')) {
      headers.add('-H "Authorization: Bearer replace-with-sanctum-token"');
    }
    if (security.contains('PartnerHmacHeaders')) {
      headers.add('-H "X-Key-Id: replace-with-key-id"');
      headers.add('-H "X-Timestamp: 2026-11-22T09:30:00Z"');
      headers.add('-H "X-Nonce: replace-with-base64-nonce"');
      headers.add('-H "X-Signature-256: sha256=replace-with-hmac-signature"');
    }
    headers.add('-H "Accept: application/json"');

    if (endpoint.requestBody != null) {
      headers.add('-H "Content-Type: application/json"');
      final example = getRequestBodyExample(endpoint.requestBody);
      if (example != null) {
        headers.add("-d '${jsonEncode(example)}'");
      }
    }

    if (headers.isNotEmpty) {
      buffer.write(' \\\n  ');
      buffer.write(headers.join(' \\\n  '));
    }

    return buffer.toString();
  }

  dynamic getRequestBodyExample(dynamic requestBody) {
    if (requestBody == null || requestBody is! Map) return null;
    final content = requestBody['content'] as Map<String, dynamic>?;
    if (content == null) return null;
    final jsonContent = content['application/json'];
    if (jsonContent == null || jsonContent is! Map) return null;
    final schema = jsonContent['schema'];
    if (schema == null || schema is! Map) return null;

    if (schema['example'] != null) return schema['example'];

    final properties = schema['properties'] as Map<String, dynamic>?;
    if (properties == null) return null;

    final example = <String, dynamic>{};
    properties.forEach((key, prop) {
      if (prop is Map && prop['example'] != null) {
        example[key] = prop['example'];
      } else if (prop is Map && prop['type'] == 'string') {
        if (prop['format'] == 'email') {
          example[key] = 'user@uls-test.edu.ph';
        } else if (prop['format'] == 'date') {
          example[key] = '2026-02-02';
        } else if (prop['format'] == 'date-time') {
          example[key] = '2026-11-22T09:30:00Z';
        } else {
          example[key] = 'example-string';
        }
      } else if (prop is Map && prop['type'] == 'integer') {
        example[key] = prop['minimum'] ?? 1;
      } else if (prop is Map && prop['type'] == 'boolean') {
        example[key] = true;
      } else if (prop is Map && prop['type'] == 'object') {
        example[key] = {};
      }
    });
    return example;
  }
}