import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import '../utils/json_utils.dart';
import 'api_envelope.dart';
import 'api_exception.dart';

class ApiClient {
  ApiClient({http.Client? httpClient})
      : _httpClient = httpClient ?? http.Client();

  final http.Client _httpClient;

  String? _token;

  String? get token => _token;

  bool get isAuthenticated => _token != null && _token!.isNotEmpty;

  void setToken(String? token) {
    _token = (token == null || token.isEmpty) ? null : token;
  }

  void clearToken() => _token = null;

  Future<ApiEnvelope> get(String path, {Map<String, String>? query}) {
    return _send('GET', path, query: query);
  }

  Future<ApiEnvelope> post(String path, {Map<String, dynamic>? body}) {
    return _send('POST', path, body: body);
  }

  Future<ApiEnvelope> put(String path, {Map<String, dynamic>? body}) {
    return _send('PUT', path, body: body);
  }

  Future<ApiEnvelope> _send(
    String method,
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) async {
    final Uri uri = Uri.parse('${AppConfig.apiBaseUrl}$path').replace(
      queryParameters: (query != null && query.isNotEmpty) ? query : null,
    );

    final Map<String, String> headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };

    final String? token = _token;
    if (token != null) {
      headers['Authorization'] = 'Bearer $token';
    }

    final String? payload = body == null ? null : jsonEncode(body);

    final http.Response response;

    try {
      response = await _dispatch(method, uri, headers, payload)
          .timeout(AppConfig.requestTimeout);
    } on TimeoutException {
      throw const ApiException(
        kind: ApiErrorKind.timeout,
        message: 'The backend API did not respond in time. Please try again.',
      );
    } on SocketException {
      throw const ApiException(
        kind: ApiErrorKind.network,
        message: 'Cannot reach the backend API. Check that the Laravel server is running.',
      );
    } on http.ClientException {
      throw const ApiException(
        kind: ApiErrorKind.network,
        message: 'Cannot reach the backend API. Check that the Laravel server is running.',
      );
    }

    final ApiEnvelope envelope;

    try {
      final Object? decoded = jsonDecode(utf8.decode(response.bodyBytes));
      envelope = ApiEnvelope.fromJson(asMap(decoded));
    } on FormatException {
      throw ApiException(
        kind: ApiErrorKind.server,
        message: 'The backend returned an unreadable response.',
        statusCode: response.statusCode,
      );
    }

    final bool successful = response.statusCode >= 200 &&
        response.statusCode < 300 &&
        envelope.success;

    if (successful) {
      return envelope;
    }

    if (response.statusCode == 401) {
      _token = null;
    }

    throw ApiException(
      kind: ApiErrorKind.fromStatus(response.statusCode),
      message: envelope.message.isEmpty
          ? 'The request could not be completed.'
          : envelope.message,
      statusCode: response.statusCode,
      fieldErrors: envelope.errors ?? const <String, List<String>>{},
    );
  }

  Future<http.Response> _dispatch(
    String method,
    Uri uri,
    Map<String, String> headers,
    String? payload,
  ) {
    switch (method) {
      case 'POST':
        return _httpClient.post(uri, headers: headers, body: payload, encoding: utf8);
      case 'PUT':
        return _httpClient.put(uri, headers: headers, body: payload, encoding: utf8);
      default:
        return _httpClient.get(uri, headers: headers);
    }
  }
}