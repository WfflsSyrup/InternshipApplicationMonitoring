enum ApiErrorKind {
  network,
  timeout,
  unauthorized,
  forbidden,
  notFound,
  conflict,
  validation,
  unavailable,
  server,
  unknown;

  static ApiErrorKind fromStatus(int? statusCode) {
    if (statusCode == null) {
      return ApiErrorKind.unknown;
    }

    return switch (statusCode) {
      401 => ApiErrorKind.unauthorized,
      403 => ApiErrorKind.forbidden,
      404 => ApiErrorKind.notFound,
      409 => ApiErrorKind.conflict,
      422 => ApiErrorKind.validation,
      503 => ApiErrorKind.unavailable,
      _ when statusCode >= 500 => ApiErrorKind.server,
      _ => ApiErrorKind.unknown,
    };
  }
}

class ApiException implements Exception {
  const ApiException({
    required this.kind,
    required this.message,
    this.statusCode,
    this.fieldErrors = const <String, List<String>>{},
  });

  final ApiErrorKind kind;
  final String message;
  final int? statusCode;
  final Map<String, List<String>> fieldErrors;

  bool get isUnauthorized => kind == ApiErrorKind.unauthorized;

  bool get isForbidden => kind == ApiErrorKind.forbidden;

  bool get isNetworkRelated =>
      kind == ApiErrorKind.network || kind == ApiErrorKind.timeout;

  bool get isRetryable => isNetworkRelated || kind == ApiErrorKind.unavailable;

  bool get isReplay => kind == ApiErrorKind.conflict;

  List<String> errorsFor(String field) => fieldErrors[field] ?? const <String>[];

  bool hasErrorsFor(String field) => fieldErrors.containsKey(field);

  Map<String, String?> firstErrorMessages() {
    return fieldErrors.map(
      (String key, List<String> value) =>
          MapEntry<String, String?>(key, value.isEmpty ? null : value.first),
    );
  }

  @override
  String toString() {
    return 'ApiException(${kind.name}, ${statusCode ?? '-'}: $message)';
  }
}