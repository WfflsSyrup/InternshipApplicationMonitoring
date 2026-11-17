import 'dart:ui' show Color;

import '../core/theme/app_theme.dart';
import '../core/utils/json_utils.dart';

enum AutomationJobStatus {
  queued,
  retrying,
  succeeded,
  manualReviewRequired,
  unknown;

  static AutomationJobStatus fromWire(String? value) {
    return switch (value) {
      'queued' => AutomationJobStatus.queued,
      'retrying' => AutomationJobStatus.retrying,
      'succeeded' => AutomationJobStatus.succeeded,
      'manual_review_required' => AutomationJobStatus.manualReviewRequired,
      _ => AutomationJobStatus.unknown,
    };
  }

  String get wire {
    return switch (this) {
      AutomationJobStatus.queued => 'queued',
      AutomationJobStatus.retrying => 'retrying',
      AutomationJobStatus.succeeded => 'succeeded',
      AutomationJobStatus.manualReviewRequired => 'manual_review_required',
      AutomationJobStatus.unknown => 'unknown',
    };
  }

  String get label {
    return switch (this) {
      AutomationJobStatus.queued => 'Queued',
      AutomationJobStatus.retrying => 'Retrying',
      AutomationJobStatus.succeeded => 'Succeeded',
      AutomationJobStatus.manualReviewRequired => 'Manual Review Required',
      AutomationJobStatus.unknown => 'Unknown',
    };
  }

  Color get color {
    return switch (this) {
      AutomationJobStatus.queued => AppColors.textSecondary,
      AutomationJobStatus.retrying => AppColors.warning,
      AutomationJobStatus.succeeded => AppColors.secondary,
      AutomationJobStatus.manualReviewRequired => AppColors.danger,
      AutomationJobStatus.unknown => AppColors.textSecondary,
    };
  }

  bool get needsCoordinatorRecovery => this == AutomationJobStatus.manualReviewRequired;

  bool get isTerminal =>
      this == AutomationJobStatus.succeeded ||
      this == AutomationJobStatus.manualReviewRequired;

  static List<AutomationJobStatus> get filterable => <AutomationJobStatus>[
    AutomationJobStatus.queued,
    AutomationJobStatus.retrying,
    AutomationJobStatus.succeeded,
    AutomationJobStatus.manualReviewRequired,
  ];
}

enum AutomationSweepType {
  incomplete,
  stalled,
  weeklySummary;

  static AutomationSweepType fromWire(String? value) {
    return switch (value) {
      'incomplete' => AutomationSweepType.incomplete,
      'stalled' => AutomationSweepType.stalled,
      'weekly_summary' => AutomationSweepType.weeklySummary,
      _ => AutomationSweepType.incomplete,
    };
  }

  String get wire {
    return switch (this) {
      AutomationSweepType.incomplete => 'incomplete',
      AutomationSweepType.stalled => 'stalled',
      AutomationSweepType.weeklySummary => 'weekly_summary',
    };
  }

  String get label {
    return switch (this) {
      AutomationSweepType.incomplete => 'Incomplete records',
      AutomationSweepType.stalled => 'Stalled escalations',
      AutomationSweepType.weeklySummary => 'Weekly summary',
    };
  }

  String get scheduleLabel {
    return switch (this) {
      AutomationSweepType.incomplete => 'Daily 08:00 · Asia/Manila',
      AutomationSweepType.stalled => 'Daily 08:30 · Asia/Manila',
      AutomationSweepType.weeklySummary => 'Friday 17:00 · Asia/Manila',
    };
  }

  String get description {
    return switch (this) {
      AutomationSweepType.incomplete =>
        'Draft or revision-requested applications with a missing requirement and no update for 48 hours.',
      AutomationSweepType.stalled =>
        'Applications stuck in submitted, requirements verified or partner submitted for 5 days or more.',
      AutomationSweepType.weeklySummary =>
        'Pipeline counts by status for the active ISO week, delivered to the coordinator.',
    };
  }
}

enum AutomationFaultMode {
  none,
  transientOnce,
  transientAlways,
  permanentInvalidRecipient;

  static AutomationFaultMode fromWire(String? value) {
    return switch (value) {
      'none' => AutomationFaultMode.none,
      'transient_once' => AutomationFaultMode.transientOnce,
      'transient_always' => AutomationFaultMode.transientAlways,
      'permanent_invalid_recipient' => AutomationFaultMode.permanentInvalidRecipient,
      _ => AutomationFaultMode.none,
    };
  }

  String? get wire {
    return switch (this) {
      AutomationFaultMode.none => null,
      AutomationFaultMode.transientOnce => 'transient_once',
      AutomationFaultMode.transientAlways => 'transient_always',
      AutomationFaultMode.permanentInvalidRecipient => 'permanent_invalid_recipient',
    };
  }

  String get label {
    return switch (this) {
      AutomationFaultMode.none => 'None (normal delivery)',
      AutomationFaultMode.transientOnce => 'Transient failure once',
      AutomationFaultMode.transientAlways => 'Transient failure always',
      AutomationFaultMode.permanentInvalidRecipient => 'Permanent invalid recipient',
    };
  }

  String get description {
    return switch (this) {
      AutomationFaultMode.none => 'Deliver the job exactly as configured.',
      AutomationFaultMode.transientOnce =>
        'Fail attempt 1 with HTTP 503, then succeed on the bounded retry.',
      AutomationFaultMode.transientAlways =>
        'Fail every attempt with HTTP 503 until retries are exhausted.',
      AutomationFaultMode.permanentInvalidRecipient =>
        'Fail attempt 1 with HTTP 422 so the job goes straight to manual review.',
    };
  }
}

class AutomationJobModel {
  const AutomationJobModel({
    required this.id,
    required this.correlationId,
    required this.idempotencyKey,
    required this.automationType,
    required this.automationTypeLabel,
    required this.recipientRole,
    required this.status,
    required this.attempts,
    required this.maxAttempts,
    required this.guidance,
    this.applicationId,
    this.applicationReferenceCode,
    this.recipientIdentifier,
    this.failureClass,
    this.lastErrorCode,
    this.lastErrorSummary,
    this.coordinatorRemarks,
    this.nextRetryAt,
    this.completedAt,
    this.createdAt,
  });

  factory AutomationJobModel.fromJson(Map<String, dynamic> json) {
    return AutomationJobModel(
      id: asInt(json['id']),
      correlationId: stringOr(json['correlation_id']),
      idempotencyKey: stringOr(json['idempotency_key']),
      applicationId: json['internship_application_id'] == null
          ? null
          : asInt(json['internship_application_id']),
      applicationReferenceCode: asString(json['application_reference_code']),
      automationType: stringOr(json['automation_type']),
      automationTypeLabel: stringOr(json['automation_type_label']),
      recipientRole: stringOr(json['recipient_role']),
      recipientIdentifier: asString(json['recipient_identifier']),
      status: AutomationJobStatus.fromWire(asString(json['status'])),
      attempts: asInt(json['attempts']),
      maxAttempts: asInt(json['max_attempts']),
      failureClass: asString(json['failure_class']),
      lastErrorCode: json['last_error_code'] == null
          ? null
          : asInt(json['last_error_code']),
      lastErrorSummary: asString(json['last_error_summary']),
      guidance: stringOr(json['guidance']),
      nextRetryAt: asString(json['next_retry_at']),
      completedAt: asString(json['completed_at']),
      coordinatorRemarks: asString(json['coordinator_remarks']),
      createdAt: asString(json['created_at']),
    );
  }

  final int id;

  final String correlationId;

  final String idempotencyKey;

  final int? applicationId;

  final String? applicationReferenceCode;

  final String automationType;

  final String automationTypeLabel;

  final String recipientRole;

  final String? recipientIdentifier;

  final AutomationJobStatus status;

  final int attempts;

  final int maxAttempts;

  final String? failureClass;

  final int? lastErrorCode;

  final String? lastErrorSummary;

  final String guidance;

  final String? nextRetryAt;

  final String? completedAt;

  final String? coordinatorRemarks;

  final String? createdAt;

  String get attemptsLabel => '$attempts/$maxAttempts';

  String get shortCorrelationId {
    if (correlationId.length <= 8) {
      return correlationId;
    }
    return correlationId.substring(0, 8);
  }

  String get failureLabel {
    return switch (failureClass) {
      'transient' => 'Transient',
      'permanent' => 'Permanent',
      _ => 'None',
    };
  }

  String get recipientLabel {
    final String? identifier = recipientIdentifier;

    if (identifier == null || identifier.isEmpty) {
      return 'No recipient recorded';
    }

    return '$recipientRole · $identifier';
  }

  bool get isPermanentFailure => failureClass == 'permanent';
}