import '../core/utils/date_format.dart';
import '../core/utils/json_utils.dart';
import 'application_action.dart';
import 'application_status.dart';
import 'status_log_model.dart';
import 'user_model.dart';

class ApplicationModel {
  const ApplicationModel({
    required this.id,
    required this.referenceCode,
    required this.status,
    required this.hostCompanyName,
    required this.targetRole,
    required this.internshipHoursRequired,
    required this.targetStartDate,
    required this.requirementsChecklist,
    this.adviser,
    this.partnerReviewer,
    this.student,
    this.interviewSchedule,
    this.interviewNotes,
    this.placementConfirmedAt,
    this.placementAcknowledgedAt,
    this.createdAt,
    this.updatedAt,
    this.statusLogs = const <StatusLogModel>[],
    this.actions = const <ApplicationAction>[],
  });

  factory ApplicationModel.fromJson(Map<String, dynamic> json) {
    return ApplicationModel(
      id: asInt(json['id']),
      referenceCode: stringOr(json['reference_code']),
      status: ApplicationStatus.fromWire(asString(json['status'])),
      hostCompanyName: stringOr(json['host_company_name']),
      targetRole: stringOr(json['target_role']),
      internshipHoursRequired: asInt(json['internship_hours_required']),
      targetStartDate: stringOr(json['target_start_date']),
      requirementsChecklist: _defaultChecklist()
        ..addAll(asBoolMap(json['requirements_checklist'])),
      adviser: _user(json['adviser']),
      partnerReviewer: _user(json['partner_reviewer']),
      student: _user(json['student']),
      interviewSchedule: asString(json['interview_schedule']),
      interviewNotes: asString(json['interview_notes']),
      placementConfirmedAt: asString(json['placement_confirmed_at']),
      placementAcknowledgedAt: asString(json['placement_acknowledged_at']),
      createdAt: asString(json['created_at']),
      updatedAt: asString(json['updated_at']),
      statusLogs: asList(json['status_logs'])
          .map((item) => StatusLogModel.fromJson(asMap(item)))
          .toList(growable: false),
      actions: asList(json['actions'])
          .map((item) => ApplicationAction.fromJson(asMap(item)))
          .toList(growable: false),
    );
  }

  static const List<String> checklistKeys = <String>[
    'resume_ready',
    'endorsement_letter',
    'parent_consent',
    'medical_clearance',
  ];

  static Map<String, bool> _defaultChecklist() {
    return <String, bool>{
      for (final key in checklistKeys) key: false,
    };
  }

  static UserModel? _user(Object? value) {
    if (value == null) {
      return null;
    }

    return UserModel.fromJson(asMap(value));
  }

  final int id;
  final String referenceCode;
  final ApplicationStatus status;
  final String hostCompanyName;
  final String targetRole;
  final int internshipHoursRequired;
  final String targetStartDate;
  final Map<String, bool> requirementsChecklist;
  final UserModel? adviser;
  final UserModel? partnerReviewer;
  final UserModel? student;
  final String? interviewSchedule;
  final String? interviewNotes;
  final String? placementConfirmedAt;
  final String? placementAcknowledgedAt;
  final String? createdAt;
  final String? updatedAt;
  final List<StatusLogModel> statusLogs;
  final List<ApplicationAction> actions;

  bool get requirementsComplete =>
      requirementsChecklist.values.every((value) => value);

  int get completedRequirements =>
      requirementsChecklist.values.where((value) => value).length;

  String get requirementsSummary =>
      '$completedRequirements of ${requirementsChecklist.length} ready';

  bool get isPlacementAcknowledged => placementAcknowledgedAt != null;

  bool get canStudentEdit =>
      student != null &&
      (status == ApplicationStatus.draft ||
          status == ApplicationStatus.revisionRequested);

  String get hostCompanyDisplay => hostCompanyName.isEmpty ? 'Unknown host' : hostCompanyName;

  String get targetRoleDisplay => targetRole.isEmpty ? 'Not specified' : targetRole;

  String get startLabel => DateFormatX.date(targetStartDate);

  String get interviewLabel => interviewSchedule == null
      ? 'Not scheduled'
      : DateFormatX.dateTime(interviewSchedule);

  String get createdLabel => DateFormatX.dateTime(createdAt);

  String get updatedLabel => DateFormatX.dateTime(updatedAt);

  String get placementConfirmedLabel => placementConfirmedAt == null
      ? 'Pending confirmation'
      : DateFormatX.dateTime(placementConfirmedAt);

  String get placementAcknowledgedLabel => placementAcknowledgedAt == null
      ? 'Awaiting student acknowledgement'
      : DateFormatX.dateTime(placementAcknowledgedAt);
}