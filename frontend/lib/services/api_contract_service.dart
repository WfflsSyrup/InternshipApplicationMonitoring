import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import '../models/api_contract_models.dart';

class ApiContractService {
  final ApiClient _apiClient;

  ApiContractService(this._apiClient);

  /// Fetches the OpenAPI 3.1 specification as JSON
  Future<Map<String, dynamic>> fetchOpenApiSpec() async {
    try {
      final envelope = await _apiClient.get('/docs/openapi.json');
      return envelope.dataAsMap();
    } on ApiException catch (e) {
      throw ApiException(
        message: 'Failed to fetch OpenAPI specification: ${e.message}',
        kind: e.kind,
      );
    }
  }

  /// Fetches the live contract audit report (coordinator only)
  Future<ApiContractReport> fetchContractAudit() async {
    try {
      final envelope = await _apiClient.get('/docs/contract-audit');
      final data = envelope.dataAsMap();
      return ApiContractReport.fromJson(data);
    } on ApiException catch (e) {
      throw ApiException(
        message: 'Failed to fetch contract audit: ${e.message}',
        kind: e.kind,
      );
    }
  }
}