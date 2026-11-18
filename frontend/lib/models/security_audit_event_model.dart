import '../../core/utils/json_utils.dart';

enum SecurityEventType {
  bolaBlocked,
  bflaBlocked,
  rateLimitExceeded,
  massAssignmentBlocked,
  payloadTooLarge,
  loginFailed,
  sessionRevoked,
  unknown;

  static SecurityEventType fromWire(String? wire) {
    return switch (wire) {
      'bola_blocked' => SecurityEventType.bolaBlocked,
      'bfla_blocked' => SecurityEventType.bflaBlocked,
      'rate_limit_exceeded' => SecurityEventType.rateLimitExceeded,
      'mass_assignment_blocked' => SecurityEventType.massAssignmentBlocked,
      'payload_too_large' => SecurityEventType.payloadTooLarge,
      'login_failed' => SecurityEventType.loginFailed,
      'session_revoked' => SecurityEventType.sessionRevoked,
      _ => SecurityEventType.unknown,
    };
  }

  String get wire => switch (this) {
    SecurityEventType.bolaBlocked => 'bola_blocked',
    SecurityEventType.bflaBlocked => 'bfla_blocked',
    SecurityEventType.rateLimitExceeded => 'rate_limit_exceeded',
    SecurityEventType.massAssignmentBlocked => 'mass_assignment_blocked',
    SecurityEventType.payloadTooLarge => 'payload_too_large',
    SecurityEventType.loginFailed => 'login_failed',
    SecurityEventType.sessionRevoked => 'session_revoked',
    SecurityEventType.unknown => 'unknown',
  };

  String get label => switch (this) {
    SecurityEventType.bolaBlocked => 'BOLA Blocked',
    SecurityEventType.bflaBlocked => 'BFLA Blocked',
    SecurityEventType.rateLimitExceeded => 'Rate Limit Exceeded',
    SecurityEventType.massAssignmentBlocked => 'Mass Assignment Blocked',
    SecurityEventType.payloadTooLarge => 'Payload Too Large',
    SecurityEventType.loginFailed => 'Login Failed',
    SecurityEventType.sessionRevoked => 'Session Revoked',
    SecurityEventType.unknown => 'Unknown',
  };
}

enum SecuritySeverity {
  info,
  warning,
  high,
  unknown;

  static SecuritySeverity fromWire(String? wire) {
    return switch (wire) {
      'info' => SecuritySeverity.info,
      'warning' => SecuritySeverity.warning,
      'high' => SecuritySeverity.high,
      _ => SecuritySeverity.unknown,
    };
  }

  String get wire => switch (this) {
    SecuritySeverity.info => 'info',
    SecuritySeverity.warning => 'warning',
    SecuritySeverity.high => 'high',
    SecuritySeverity.unknown => 'unknown',
  };

  String get label => switch (this) {
    SecuritySeverity.info => 'Info',
    SecuritySeverity.warning => 'Warning',
    SecuritySeverity.high => 'High',
    SecuritySeverity.unknown => 'Unknown',
  };
}

class SecurityAuditEventModel {
  const SecurityAuditEventModel({
    required this.id,
    required this.correlationId,
    required this.eventType,
    required this.severity,
    required this.endpoint,
    required this.httpStatus,
    required this.ipHashPrefix,
    this.actorId,
    this.actorRole,
    this.details,
    this.createdAt,
  });

  factory SecurityAuditEventModel.fromJson(Map<String, dynamic> json) {
    return SecurityAuditEventModel(
      id: json['id'] as int? ?? 0,
      correlationId: asString(json['correlation_id']) ?? '',
      eventType: SecurityEventType.fromWire(asString(json['event_type'])),
      severity: SecuritySeverity.fromWire(asString(json['severity'])),
      endpoint: asString(json['endpoint']) ?? '',
      httpStatus: json['http_status'] as int? ?? 0,
      ipHashPrefix: asString(json['ip_hash_prefix']) ?? '',
      actorId: json['actor_id'] as int?,
      actorRole: asString(json['actor_role']),
      details: json['details'] is Map
          ? Map<String, dynamic>.from(json['details'] as Map)
          : null,
      createdAt: asString(json['created_at']),
    );
  }

  final int id;
  final String correlationId;
  final SecurityEventType eventType;
  final SecuritySeverity severity;
  final String endpoint;
  final int httpStatus;
  final String ipHashPrefix;
  final int? actorId;
  final String? actorRole;
  final Map<String, dynamic>? details;
  final String? createdAt;

  String get eventLabel => eventType.label;

  String get severityLabel => severity.label;

  String get shortCorrelationId {
    if (correlationId.length <= 8) {
      return correlationId;
    }

    return correlationId.substring(0, 8);
  }
}