import '../core/network/api_client.dart';
import '../core/network/api_envelope.dart';
import '../core/utils/json_utils.dart';
import '../models/user_model.dart';

class AuthService {
  AuthService(this._client);

  final ApiClient _client;

  Future<UserModel> login(String email, String password) async {
    final ApiEnvelope envelope = await _client.post(
      '/auth/login',
      body: <String, dynamic>{
        'email': email.trim(),
        'password': password,
      },
    );

    final data = envelope.dataAsMap();
    final token = asString(data['token']);

    if (token == null || token.isEmpty) {
      throw StateError('The backend did not return a session token.');
    }

    _client.setToken(token);

    return UserModel.fromJson(asMap(data['user']));
  }

  Future<UserModel> me() async {
    final ApiEnvelope envelope = await _client.get('/auth/me');

    return UserModel.fromJson(envelope.dataAsMap());
  }

  Future<void> logout() async {
    try {
      await _client.post('/auth/logout');
    } finally {
      _client.clearToken();
    }
  }
}