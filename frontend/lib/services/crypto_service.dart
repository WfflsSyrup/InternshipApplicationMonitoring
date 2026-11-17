import '../core/network/api_client.dart';
import '../core/network/api_envelope.dart';
import '../core/utils/json_utils.dart';
import '../models/crypto_envelope_model.dart';
import '../models/crypto_keyring_model.dart';
import '../models/crypto_simulation_result.dart';

class CryptoService {
  CryptoService(this._client);

  final ApiClient _client;

  Future<List<CryptoEnvelopeModel>> envelopes({
    CryptoVerificationStatus? status,
    CryptoDirection? direction,
    int? applicationId,
  }) async {
    final Map<String, String> query = <String, String>{};

    if (status != null && status != CryptoVerificationStatus.unknown) {
      query['status'] = status.wire;
    }

    if (direction != null && direction.isFilterable) {
      query['direction'] = direction.wire;
    }

    if (applicationId != null) {
      query['application_id'] = '$applicationId';
    }

    final ApiEnvelope envelope = await _client.get(
      '/integrations/envelopes',
      query: query.isEmpty ? null : query,
    );

    return envelope
        .dataAsList()
        .map((Object? item) => CryptoEnvelopeModel.fromJson(asMap(item)))
        .toList(growable: false);
  }

  Future<CryptoKeyringStatus> keyringStatus() async {
    final ApiEnvelope envelope = await _client.get(
      '/integrations/keyring/status',
    );

    return CryptoKeyringStatus.fromJson(envelope.dataAsMap());
  }

  Future<CryptoEnvelopeModel> dispatchPartnerPacket({
    required int applicationId,
    String? remarks,
  }) async {
    final String? note = remarks?.trim();

    final ApiEnvelope envelope = await _client.post(
      '/integrations/partner-packets/dispatch',
      body: <String, dynamic>{
        'application_id': applicationId,
        if (note != null && note.isNotEmpty) 'remarks': note,
      },
    );

    return CryptoEnvelopeModel.fromJson(envelope.dataAsMap());
  }

  Future<CryptoSimulationResult> runSimulation({
    required CryptoSimulationMode mode,
    String? referenceCode,
    String? outcome,
  }) async {
    final String? reference = referenceCode?.trim();
    final String? result = outcome?.trim();

    final ApiEnvelope envelope = await _client.post(
      '/integrations/verify-simulation',
      body: <String, dynamic>{
        'mode': mode.wire,
        if (reference != null && reference.isNotEmpty)
          'reference_code': reference,
        if (result != null && result.isNotEmpty) 'outcome': result,
      },
    );

    return CryptoSimulationResult.fromJson(envelope.dataAsMap());
  }
}