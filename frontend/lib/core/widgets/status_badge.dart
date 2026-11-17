import 'package:flutter/material.dart';

import '../../models/application_status.dart';
import '../theme/app_theme.dart';

class StatusBadge extends StatelessWidget {
  const StatusBadge({
    super.key,
    required this.status,
    this.label,
    this.compact = false,
  });

  final ApplicationStatus status;
  final String? label;
  final bool compact;

  static Color colorFor(ApplicationStatus status) {
    return switch (status) {
      ApplicationStatus.draft => AppColors.textSecondary,
      ApplicationStatus.submitted => AppColors.info,
      ApplicationStatus.requirementsVerified => AppColors.primary,
      ApplicationStatus.revisionRequested => AppColors.warning,
      ApplicationStatus.endorsed => AppColors.secondary,
      ApplicationStatus.partnerSubmitted => const Color(0xFF2DD4BF),
      ApplicationStatus.interviewScheduled => const Color(0xFF818CF8),
      ApplicationStatus.interviewPassed => AppColors.secondary,
      ApplicationStatus.interviewFailed => AppColors.danger,
      ApplicationStatus.placementConfirmed => const Color(0xFF4ADE80),
      ApplicationStatus.withdrawn => AppColors.textSecondary,
      ApplicationStatus.rejected => AppColors.danger,
    };
  }

  @override
  Widget build(BuildContext context) {
    final Color accent = colorFor(status);

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 10 : 12,
        vertical: compact ? 4 : 6,
      ),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: accent.withValues(alpha: 0.5)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(color: accent, shape: BoxShape.circle),
          ),
          const SizedBox(width: 7),
          Text(
            label ?? status.label,
            style: TextStyle(
              color: accent,
              fontSize: compact ? 11.5 : 12.5,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }
}