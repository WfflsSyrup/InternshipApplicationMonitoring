import '../core/network/api_client.dart';
import '../core/network/api_envelope.dart';
import '../core/utils/json_utils.dart';
import '../models/automation_job_model.dart';
import '../models/automation_sweep_result.dart';

class AutomationService {
  AutomationService(this._client);

  final ApiClient _client;

  Future<List<AutomationJobModel>> jobs({
    AutomationJobStatus? status,
    int? applicationId,
  }) async {
    final Map<String, String> query = <String, String>{};

    if (status != null && status != AutomationJobStatus.unknown) {
      query['status'] = status.wire;
    }

    if (applicationId != null) {
      query['application_id'] = '$applicationId';
    }

    final ApiEnvelope envelope = await _client.get(
      '/automation/jobs',
      query: query.isEmpty ? null : query,
    );

    return envelope
        .dataAsList()
        .map((Object? item) => AutomationJobModel.fromJson(asMap(item)))
        .toList(growable: false);
  }

  Future<AutomationSweepResult> runSweep({
    required AutomationSweepType type,
    AutomationFaultMode faultMode = AutomationFaultMode.none,
  }) async {
    final String? wireFaultMode = faultMode.wire;

    final Map<String, dynamic> body = <String, dynamic>{'type': type.wire};

    if (wireFaultMode != null) {
      body['fault_mode'] = wireFaultMode;
    }

    final ApiEnvelope envelope = await _client.post(
      '/automation/sweeps/run',
      body: body,
    );

    return AutomationSweepResult.fromJson(envelope.dataAsMap());
  }

  Future<AutomationJobModel> requeue(
    int jobId, {
    String? recipientIdentifier,
    String? remarks,
  }) async {
    final String? identifier = recipientIdentifier?.trim();
    final String? note = remarks?.trim();

    final ApiEnvelope envelope = await _client.post(
      '/automation/jobs/$jobId/requeue',
      body: <String, dynamic>{
        if (identifier != null && identifier.isNotEmpty)
          'recipient_identifier': identifier,
        if (note != null && note.isNotEmpty) 'remarks': note,
      },
    );

    return AutomationJobModel.fromJson(asMap(asMap(envelope.data)['job']));
  }

  Future<AutomationWeeklySummary?> weeklySummary() async {
    final ApiEnvelope envelope = await _client.get(
      '/automation/summaries/weekly',
    );

    final Object? data = envelope.data;

    if (data == null) {
      return null;
    }

    return AutomationWeeklySummary.fromJson(asMap(data));
  }
}