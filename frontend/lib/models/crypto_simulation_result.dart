import '../core/utils/json_utils.dart';
import 'crypto_envelope_model.dart';

enum CryptoSimulationMode {
  validCallback,
  tamperCiphertext,
  forgedSignature,
  replayNonce,
  expiredTimestamp,
  unknown;

  static CryptoSimulationMode fromWire(String? wire) {
    return switch (wire) {
      'valid_callback' => CryptoSimulationMode.validCallback,
      'tamper_ciphertext' => CryptoSimulationMode.tamperCiphertext,
      'forged_signature' => CryptoSimulationMode.forgedSignature,
      'replay_nonce' => CryptoSimulationMode.replayNonce,
      'expired_timestamp' => CryptoSimulationMode.expiredTimestamp,
      _ => CryptoSimulationMode.unknown,
    };
  }

  String get wire {
    return switch (this) {
      CryptoSimulationMode.validCallback => 'valid_callback',
      CryptoSimulationMode.tamperCiphertext => 'tamper_ciphertext',
      CryptoSimulationMode.forgedSignature => 'forged_signature',
      CryptoSimulationMode.replayNonce => 'replay_nonce',
      CryptoSimulationMode.expiredTimestamp => 'expired_timestamp',
      CryptoSimulationMode.unknown => 'unknown',
    };
  }

  String get label {
    return switch (this) {
      CryptoSimulationMode.validCallback => 'Valid callback',
      CryptoSimulationMode.tamperCiphertext => 'Tampered ciphertext',
      CryptoSimulationMode.forgedSignature => 'Forged signature',
      CryptoSimulationMode.replayNonce => 'Replay attack',
      CryptoSimulationMode.expiredTimestamp => 'Expired timestamp',
      CryptoSimulationMode.unknown => 'Unknown scenario',
    };
  }

  String get description {
    return switch (this) {
      CryptoSimulationMode.validCallback =>
        'A correctly sealed and signed inbound callback. Expected to verify.',
      CryptoSimulationMode.tamperCiphertext =>
        'The last ciphertext byte is flipped and the request re-signed, so only '
            'the AES-256-GCM tag can catch it. Expected 422.',
      CryptoSimulationMode.forgedSignature =>
        'A valid envelope with an HMAC signature produced from an unknown '
            'secret. Expected 401.',
      CryptoSimulationMode.replayNonce =>
        'The same nonce is delivered twice; the second delivery must be '
            'rejected as a replay. Expected 409.',
      CryptoSimulationMode.expiredTimestamp =>
        'A correctly signed request stamped far outside the clock-skew window. '
            'Expected 401.',
      CryptoSimulationMode.unknown => 'No scenario selected.',
    };
  }

  bool get expectsAcceptance => this == CryptoSimulationMode.validCallback;

  bool get isSelectable => this != CryptoSimulationMode.unknown;
}

class CryptoSimulationStep {
  const CryptoSimulationStep({
    required this.name,
    required this.result,
    required this.detail,
  });

  factory CryptoSimulationStep.fromJson(Map<String, dynamic> json) {
    return CryptoSimulationStep(
      name: stringOr(json['name'], '—'),
      result: stringOr(json['result'], '—'),
      detail: stringOr(json['detail'], '—'),
    );
  }

  final String name;

  final String result;

  final String detail;
}

class CryptoSimulationResult {
  const CryptoSimulationResult({
    required this.mode,
    required this.expectedOutcome,
    required this.accepted,
    required this.matchedExpectation,
    required this.httpStatus,
    required this.reasonCode,
    required this.message,
    required this.referenceCode,
    required this.outcome,
    required this.keyId,
    required this.cipherSuite,
    required this.correlationId,
    required this.envelopeId,
    required this.durationMs,
    required this.steps,
    this.firstAttempt,
  });

  factory CryptoSimulationResult.fromJson(Map<String, dynamic> json) {
    return CryptoSimulationResult(
      mode: CryptoSimulationMode.fromWire(asString(json['mode'])),
      expectedOutcome: stringOr(json['expected_outcome'], '—'),
      accepted: asBool(json['accepted']),
      matchedExpectation: asBool(json['matched_expectation']),
      httpStatus: asInt(json['http_status']),
      reasonCode: asString(json['reason_code']),
      message: stringOr(json['message'], ''),
      referenceCode: stringOr(json['reference_code'], '—'),
      outcome: stringOr(json['outcome'], '—'),
      keyId: stringOr(json['key_id'], '—'),
      cipherSuite: stringOr(json['cipher_suite'], '—'),
      correlationId: stringOr(json['correlation_id'], '—'),
      envelopeId: json['envelope_id'] == null ? null : asInt(json['envelope_id']),
      durationMs: asInt(json['duration_ms']),
      steps: asList(json['steps'])
          .map((Object? item) => CryptoSimulationStep.fromJson(asMap(item)))
          .toList(growable: false),
      firstAttempt: json['first_attempt'] == null
          ? null
          : CryptoSimulationStep.fromJson(asMap(json['first_attempt'])),
    );
  }

  final CryptoSimulationMode mode;

  final String expectedOutcome;

  final bool accepted;

  final bool matchedExpectation;

  final int httpStatus;

  final String? reasonCode;

  final String message;

  final String referenceCode;

  final String outcome;

  final String keyId;

  final String cipherSuite;

  final String correlationId;

  final int? envelopeId;

  final int durationMs;

  final List<CryptoSimulationStep> steps;

  final CryptoSimulationStep? firstAttempt;

  CryptoVerificationStatus get verificationStatus {
    if (accepted) {
      return CryptoVerificationStatus.verified;
    }

    return CryptoVerificationStatus.fromWire(reasonCode);
  }

  String get outcomeLabel {
    if (accepted) {
      return 'Accepted · HTTP $httpStatus';
    }

    final String code = reasonCode ?? 'rejected';

    return 'Rejected · $code · HTTP $httpStatus';
  }

  String get expectationLabel {
    return matchedExpectation
        ? 'Matched expectation'
        : 'Unexpected result for ${mode.label.toLowerCase()}';
  }
}