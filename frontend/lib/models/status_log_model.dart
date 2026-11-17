import 'application_status.dart';

class StatusLogModel {
  const StatusLogModel({
    required this.id,
    required this.actorId,
    this.actorName,
    required this.actorRole,
    this.fromStatus,
    required this.toStatus,
    this.remarks,
    required this.createdAt,
  });

  factory StatusLogModel.fromJson(Map<String, dynamic> json) {
    final from = json['from_status']?.toString();
    final to = json['to_status']?.toString();

    return StatusLogModel(
      id: _int(json['id']),
      actorId: _int(json['actor_id']),
      actorName: _nullable(json['actor_name']),
      actorRole: json['actor_role']?.toString() ?? '',
      fromStatus: from == null ? null : ApplicationStatus.fromWire(from),
      toStatus: ApplicationStatus.fromWire(to),
      remarks: _nullable(json['remarks']),
      createdAt: json['created_at']?.toString() ?? '',
    );
  }

  final int id;
  final int actorId;
  final String? actorName;
  final String actorRole;
  final ApplicationStatus? fromStatus;
  final ApplicationStatus toStatus;
  final String? remarks;
  final String createdAt;

  String get fromStatusLabel => fromStatus?.label ?? 'Start';

  String get toStatusLabel => toStatus.label;

  String get actorRoleLabel {
    return switch (actorRole) {
      'student' => 'Student',
      'adviser' => 'Adviser',
      'coordinator' => 'Coordinator',
      'partner_reviewer' => 'Partner Reviewer',
      _ => actorRole.isEmpty ? 'System' : actorRole,
    };
  }

  DateTime? get timestamp => DateTime.tryParse(createdAt);

  static int _int(Object? value) {
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static String? _nullable(Object? value) {
    if (value == null) {
      return null;
    }
    final text = value.toString().trim();
    return text.isEmpty ? null : text;
  }
}