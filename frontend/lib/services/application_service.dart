import '../core/network/api_client.dart';
import '../core/network/api_envelope.dart';
import '../core/utils/json_utils.dart';
import '../models/application_model.dart';
import '../models/application_status.dart';
import '../models/user_model.dart';

class ApplicationService {
  ApplicationService(this._client);

  final ApiClient _client;

  Future<List<ApplicationModel>> list({ApplicationStatus? status}) async {
    final ApiEnvelope envelope = await _client.get(
      '/applications',
      query: status == null
          ? null
          : <String, String>{'status': status.wire},
    );

    return envelope
        .dataAsList()
        .map((item) => ApplicationModel.fromJson(asMap(item)))
        .toList(growable: false);
  }

  Future<ApplicationModel> show(int id) async {
    final ApiEnvelope envelope = await _client.get('/applications/$id');

    return ApplicationModel.fromJson(envelope.dataAsMap());
  }

  Future<ApplicationModel> create(Map<String, dynamic> payload) async {
    final ApiEnvelope envelope = await _client.post(
      '/applications',
      body: payload,
    );

    return ApplicationModel.fromJson(envelope.dataAsMap());
  }

  Future<ApplicationModel> update(int id, Map<String, dynamic> payload) async {
    final ApiEnvelope envelope = await _client.put(
      '/applications/$id',
      body: payload,
    );

    return ApplicationModel.fromJson(envelope.dataAsMap());
  }

  Future<ApplicationModel> transition(int id, Map<String, dynamic> payload) async {
    final ApiEnvelope envelope = await _client.post(
      '/applications/$id/transition',
      body: payload,
    );

    return ApplicationModel.fromJson(envelope.dataAsMap());
  }

  Future<List<UserModel>> partnerReviewers() async {
    final ApiEnvelope envelope = await _client.get('/partner-reviewers');

    return envelope
        .dataAsList()
        .map((item) => UserModel.fromJson(asMap(item)))
        .toList(growable: false);
  }
}