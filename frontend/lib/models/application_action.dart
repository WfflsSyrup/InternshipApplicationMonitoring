import 'application_status.dart';

class ApplicationAction {
  const ApplicationAction({
    required this.status,
    required this.label,
    this.requiresPartnerReviewer = false,
    this.requiresInterviewSchedule = false,
    this.requiresRemarks = false,
  });

  factory ApplicationAction.fromJson(Map<String, dynamic> json) {
    return ApplicationAction(
      status: ApplicationStatus.fromWire(json['status']?.toString()),
      label: json['label']?.toString() ?? 'Update Status',
      requiresPartnerReviewer: json['requires_partner_reviewer'] == true,
      requiresInterviewSchedule: json['requires_interview_schedule'] == true,
      requiresRemarks: json['requires_remarks'] == true,
    );
  }

  final ApplicationStatus status;
  final String label;
  final bool requiresPartnerReviewer;
  final bool requiresInterviewSchedule;
  final bool requiresRemarks;
}