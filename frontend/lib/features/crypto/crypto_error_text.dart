import '../../core/network/api_exception.dart';

String describeCryptoError(ApiException error) {
  if (error.isForbidden) {
    return 'Only the internship coordinator can perform this cryptographic '
        'action. This account has read-only access to the audit trail.';
  }

  if (error.isReplay) {
    return 'Replay detected (HTTP 409): this nonce was already used, so the '
        'request was rejected before decryption.';
  }

  if (error.kind == ApiErrorKind.unavailable) {
    return 'The cryptographic keyring is not configured for this environment. '
        'Set the CRYPTO_KEY_* values in the backend .env.';
  }

  if (error.kind == ApiErrorKind.validation) {
    final List<String> messages = error.fieldErrors.values
        .expand((List<String> items) => items)
        .where((String message) => message.trim().isNotEmpty)
        .toList(growable: false);

    return messages.isEmpty ? error.message : messages.first;
  }

  if (error.isNetworkRelated) {
    return error.message;
  }

  return error.message;
}