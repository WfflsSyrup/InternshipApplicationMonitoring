enum ApplicationStatus {
  draft,
  submitted,
  requirementsVerified,
  revisionRequested,
  endorsed,
  partnerSubmitted,
  interviewScheduled,
  interviewPassed,
  interviewFailed,
  placementConfirmed,
  withdrawn,
  rejected;

  static ApplicationStatus fromWire(String? value) {
    return switch (value) {
      'draft' => ApplicationStatus.draft,
      'submitted' => ApplicationStatus.submitted,
      'requirements_verified' => ApplicationStatus.requirementsVerified,
      'revision_requested' => ApplicationStatus.revisionRequested,
      'endorsed' => ApplicationStatus.endorsed,
      'partner_submitted' => ApplicationStatus.partnerSubmitted,
      'interview_scheduled' => ApplicationStatus.interviewScheduled,
      'interview_passed' => ApplicationStatus.interviewPassed,
      'interview_failed' => ApplicationStatus.interviewFailed,
      'placement_confirmed' => ApplicationStatus.placementConfirmed,
      'withdrawn' => ApplicationStatus.withdrawn,
      'rejected' => ApplicationStatus.rejected,
      _ => ApplicationStatus.draft,
    };
  }

  String get wire {
    return switch (this) {
      ApplicationStatus.draft => 'draft',
      ApplicationStatus.submitted => 'submitted',
      ApplicationStatus.requirementsVerified => 'requirements_verified',
      ApplicationStatus.revisionRequested => 'revision_requested',
      ApplicationStatus.endorsed => 'endorsed',
      ApplicationStatus.partnerSubmitted => 'partner_submitted',
      ApplicationStatus.interviewScheduled => 'interview_scheduled',
      ApplicationStatus.interviewPassed => 'interview_passed',
      ApplicationStatus.interviewFailed => 'interview_failed',
      ApplicationStatus.placementConfirmed => 'placement_confirmed',
      ApplicationStatus.withdrawn => 'withdrawn',
      ApplicationStatus.rejected => 'rejected',
    };
  }

  String get label {
    return switch (this) {
      ApplicationStatus.draft => 'Draft',
      ApplicationStatus.submitted => 'Submitted',
      ApplicationStatus.requirementsVerified => 'Requirements Verified',
      ApplicationStatus.revisionRequested => 'Revision Requested',
      ApplicationStatus.endorsed => 'Endorsed',
      ApplicationStatus.partnerSubmitted => 'Submitted To Partner',
      ApplicationStatus.interviewScheduled => 'Interview Scheduled',
      ApplicationStatus.interviewPassed => 'Interview Passed',
      ApplicationStatus.interviewFailed => 'Interview Failed',
      ApplicationStatus.placementConfirmed => 'Placement Confirmed',
      ApplicationStatus.withdrawn => 'Withdrawn',
      ApplicationStatus.rejected => 'Rejected',
    };
  }

  bool get isActive {
    return switch (this) {
      ApplicationStatus.draft ||
      ApplicationStatus.submitted ||
      ApplicationStatus.requirementsVerified ||
      ApplicationStatus.revisionRequested ||
      ApplicationStatus.endorsed ||
      ApplicationStatus.partnerSubmitted ||
      ApplicationStatus.interviewScheduled ||
      ApplicationStatus.interviewPassed ||
      ApplicationStatus.interviewFailed => true,
      _ => false,
    };
  }

  bool get isTerminal {
    return switch (this) {
      ApplicationStatus.withdrawn ||
      ApplicationStatus.rejected ||
      ApplicationStatus.placementConfirmed => true,
      _ => false,
    };
  }

  static List<ApplicationStatus> get valuesInWorkflow => ApplicationStatus.values
      .where((status) => status.isActive)
      .toList(growable: false);
}