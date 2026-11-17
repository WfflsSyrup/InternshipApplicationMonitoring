import 'dart:ui' show Color;

import '../core/theme/app_theme.dart';
import '../core/utils/json_utils.dart';

enum CryptoVerificationStatus {
  verified,
  rejectedSignature,
  rejectedTamper,
  rejectedReplay,
  rejectedExpired,
  unknown;

  static CryptoVerificationStatus fromWire(String? wire) {
    return switch (wire) {
      'verified' => CryptoVerificationStatus.verified,
      'rejected_signature' => CryptoVerificationStatus.rejectedSignature,
      'rejected_tamper' => CryptoVerificationStatus.rejectedTamper,
      'rejected_replay' => CryptoVerificationStatus.rejectedReplay,
      'rejected_expired' => CryptoVerificationStatus.rejectedExpired,
      _ => CryptoVerificationStatus.unknown,
    };
  }

  String get wire {
    return switch (this) {
      CryptoVerificationStatus.verified => 'verified',
      CryptoVerificationStatus.rejectedSignature => 'rejected_signature',
      CryptoVerificationStatus.rejectedTamper => 'rejected_tamper',
      CryptoVerificationStatus.rejectedReplay => 'rejected_replay',
      CryptoVerificationStatus.rejectedExpired => 'rejected_expired',
      CryptoVerificationStatus.unknown => 'unknown',
    };
  }

  String get label {
    return switch (this) {
      CryptoVerificationStatus.verified => 'Verified',
      CryptoVerificationStatus.rejectedSignature => 'Rejected: Signature',
      CryptoVerificationStatus.rejectedTamper => 'Rejected: Tamper',
      CryptoVerificationStatus.rejectedReplay => 'Rejected: Replay',
      CryptoVerificationStatus.rejectedExpired => 'Rejected: Expired',
      CryptoVerificationStatus.unknown => 'Unknown',
    };
  }

  String get reasonCode {
    return switch (this) {
      CryptoVerificationStatus.verified => 'verified',
      CryptoVerificationStatus.rejectedSignature => 'rejected_signature',
      CryptoVerificationStatus.rejectedTamper => 'rejected_tamper',
      CryptoVerificationStatus.rejectedReplay => 'rejected_replay',
      CryptoVerificationStatus.rejectedExpired => 'rejected_expired',
      CryptoVerificationStatus.unknown => 'unknown',
    };
  }

  Color get color {
    return switch (this) {
      CryptoVerificationStatus.verified => AppColors.secondary,
      CryptoVerificationStatus.rejectedSignature => AppColors.danger,
      CryptoVerificationStatus.rejectedTamper => AppColors.danger,
      CryptoVerificationStatus.rejectedReplay => AppColors.warning,
      CryptoVerificationStatus.rejectedExpired => AppColors.warning,
      CryptoVerificationStatus.unknown => AppColors.textSecondary,
    };
  }

  bool get isVerified => this == CryptoVerificationStatus.verified;
}

enum CryptoDirection {
  outbound,
  inbound,
  unknown;

  static CryptoDirection fromWire(String? wire) {
    return switch (wire) {
      'outbound_partner_packet' => CryptoDirection.outbound,
      'inbound_partner_callback' => CryptoDirection.inbound,
      _ => CryptoDirection.unknown,
    };
  }

  String get wire {
    return switch (this) {
      CryptoDirection.outbound => 'outbound_partner_packet',
      CryptoDirection.inbound => 'inbound_partner_callback',
      CryptoDirection.unknown => 'unknown',
    };
  }

  String get label {
    return switch (this) {
      CryptoDirection.outbound => 'Outbound partner packet',
      CryptoDirection.inbound => 'Inbound partner callback',
      CryptoDirection.unknown => 'Unknown direction',
    };
  }

  bool get isFilterable => this != CryptoDirection.unknown;
}

class CryptoEnvelopeModel {
  const CryptoEnvelopeModel({
    required this.id,
    required this.correlationId,
    required this.applicationId,
    required this.applicationRef,
    required this.direction,
    required this.keyId,
    required this.cipherSuite,
    required this.signatureVerified,
    required this.replayChecked,
    required this.status,
    required this.failureReason,
    required this.payloadSha256,
    required this.createdAt,
  });

  factory CryptoEnvelopeModel.fromJson(Map<String, dynamic> json) {
    return CryptoEnvelopeModel(
      id: asInt(json['id']),
      correlationId: stringOr(json['correlation_id'], '—'),
      applicationId: json['application_id'] == null
          ? null
          : asInt(json['application_id']),
      applicationRef: asString(json['application_ref']),
      direction: CryptoDirection.fromWire(asString(json['direction'])),
      keyId: stringOr(json['key_id'], '—'),
      cipherSuite: stringOr(
        json['cipher_suite'],
        'AES-256-GCM+HMAC-SHA256',
      ),
      signatureVerified: asBool(json['signature_verified']),
      replayChecked: asBool(json['replay_checked']),
      status: CryptoVerificationStatus.fromWire(
        asString(json['verification_status']),
      ),
      failureReason: asString(json['failure_reason']),
      payloadSha256: stringOr(json['payload_sha256'], ''),
      createdAt: asString(json['created_at']),
    );
  }

  final int id;

  final String correlationId;

  final int? applicationId;

  final String? applicationRef;

  final CryptoDirection direction;

  final String keyId;

  final String cipherSuite;

  final bool signatureVerified;

  final bool replayChecked;

  final CryptoVerificationStatus status;

  final String? failureReason;

  final String payloadSha256;

  final String? createdAt;

  String get shortCorrelationId {
    if (correlationId.length <= 18) {
      return correlationId;
    }

    return '${correlationId.substring(0, 18)}…';
  }

  String get shaPrefix {
    if (payloadSha256.length < 16) {
      return payloadSha256.isEmpty ? '—' : payloadSha256;
    }

    return 'sha256:${payloadSha256.substring(0, 12)}…';
  }

  bool get isSimulation => correlationId.toUpperCase().startsWith('SIM-');

  bool get isRejected => !status.isVerified;
}
