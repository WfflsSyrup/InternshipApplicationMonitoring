import '../core/network/api_client.dart';
import '../core/network/api_envelope.dart';
import '../core/utils/json_utils.dart';
import '../models/security_audit_event_model.dart';
import '../models/security_posture_model.dart';

class SecurityService {
  SecurityService(this._client);

  final ApiClient _client;

  Future<List<SecurityAuditEventModel>> auditEvents({
    SecurityEventType? eventType,
    SecuritySeverity? severity,
    int perPage = 25,
  }) async {
    final Map<String, String> query = <String, String>{'per_page': '$perPage'};

    if (eventType != null && eventType != SecurityEventType.unknown) {
      query['event_type'] = eventType.wire;
    }

    if (severity != null && severity != SecuritySeverity.unknown) {
      query['severity'] = severity.wire;
    }

    final ApiEnvelope envelope = await _client.get(
      '/security/audit-events',
      query: query,
    );

    return envelope
        .dataAsList()
        .map((Object? item) =>
            SecurityAuditEventModel.fromJson(asMap(item)))
        .toList(growable: false);
  }

  Future<SecurityPostureModel> posture() async {
    final ApiEnvelope envelope = await _client.get('/security/posture');

    return SecurityPostureModel.fromJson(envelope.dataAsMap());
  }
}