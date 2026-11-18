enum ApiErrorKind {
  network,
  timeout,
  unauthorized,
  forbidden,
  notFound,
  conflict,
  rateLimited,
  payloadTooLarge,
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
      413 => ApiErrorKind.payloadTooLarge,
      422 => ApiErrorKind.validation,
      429 => ApiErrorKind.rateLimited,
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
    this.retryAfterSeconds,
  });

  final ApiErrorKind kind;
  final String message;
  final int? statusCode;
  final Map<String, List<String>> fieldErrors;

  /// Server-provided cooldown in seconds, read from `Retry-After` (429).
  final int? retryAfterSeconds;

  bool get isUnauthorized => kind == ApiErrorKind.unauthorized;

  bool get isForbidden => kind == ApiErrorKind.forbidden;

  bool get isNetworkRelated =>
      kind == ApiErrorKind.network || kind == ApiErrorKind.timeout;

  bool get isRetryable =>
      isNetworkRelated || kind == ApiErrorKind.unavailable || isRateLimited;

  bool get isReplay => kind == ApiErrorKind.conflict;

  bool get isRateLimited => kind == ApiErrorKind.rateLimited;

  bool get isPayloadTooLarge => kind == ApiErrorKind.payloadTooLarge;

  String get cooldownLabel {
    final int? seconds = retryAfterSeconds;

    if (seconds == null || seconds <= 0) {
      return 'Please wait a moment before trying again.';
    }

    if (seconds < 60) {
      return 'You can retry in about $seconds second${seconds == 1 ? '' : 's'}.';
    }

    final int minutes = (seconds / 60).ceil();

    return 'You can retry in about $minutes minute${minutes == 1 ? '' : 's'}.';
  }

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