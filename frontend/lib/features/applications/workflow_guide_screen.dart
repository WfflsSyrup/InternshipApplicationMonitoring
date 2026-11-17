import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../models/application_status.dart';

class WorkflowGuideScreen extends StatelessWidget {
  const WorkflowGuideScreen({super.key});

  static const List<(ApplicationStatus, String, String)> _steps =
      <(ApplicationStatus, String, String)>[
    (
      ApplicationStatus.draft,
      'Student prepares the application',
      'The student records the host company, target role, required hours, '
          'target start date, and requirement checklist before submitting.',
    ),
    (
      ApplicationStatus.submitted,
      'Adviser or coordinator verifies requirements',
      'Academic requirements are checked against the curriculum checklist. '
          'The application is either verified or returned with a revision request.',
    ),
    (
      ApplicationStatus.endorsed,
      'Adviser issues academic endorsement',
      'Once requirements are verified, the adviser endorses the student for '
          'partner deployment.',
    ),
    (
      ApplicationStatus.partnerSubmitted,
      'Coordinator routes to a partner reviewer',
      'The coordinator selects the partner reviewer who will handle the '
          'interview and placement process.',
    ),
    (
      ApplicationStatus.interviewScheduled,
      'Partner reviewer schedules the interview',
      'A date, time, and optional notes are recorded for the partner interview.',
    ),
    (
      ApplicationStatus.placementConfirmed,
      'Interview outcome and placement',
      'The partner reviewer records the outcome. When the interview is passed, '
          'the coordinator confirms the placement and the student acknowledges it.',
    ),
  ];

  static const List<(String, String, IconData)> _roles =
      <(String, String, IconData)>[
    (
      'Student',
      'Submits applications, uploads requirement metadata, tracks status, and '
          'acknowledges placement.',
      Icons.school_rounded,
    ),
    (
      'Adviser',
      'Verifies academic requirements, requests revisions, and issues '
          'endorsements.',
      Icons.fact_check_rounded,
    ),
    (
      'Coordinator',
      'Oversees the pipeline, validates completeness, routes endorsed '
          'applications, and confirms placements.',
      Icons.manage_accounts_rounded,
    ),
    (
      'Partner Reviewer',
      'Reviews partner submissions, schedules interviews, records outcomes, and '
          'submits placement offers.',
      Icons.handshake_rounded,
    ),
  ];

  Widget _sectionTitle(BuildContext context, String title, IconData icon) {
    final ThemeData theme = Theme.of(context);

    return Row(
      children: <Widget>[
        Icon(icon, size: 19, color: AppColors.primary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.titleLarge,
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
        children: <Widget>[
          Text('Workflow guide', style: theme.textTheme.displaySmall?.copyWith(fontSize: 22)),
          const SizedBox(height: 6),
          Text(
            'This build covers the core bounded workflow only. Scheduled '
            'automation workers, cryptographic payload wrappers, load testing, '
            'and UAT reporting are intentionally out of scope for this '
            'foundation and can be layered on later.',
            style: theme.textTheme.bodySmall?.copyWith(height: 1.5),
          ),
          const SizedBox(height: 20),
          _sectionTitle(context, 'Roles and responsibilities', Icons.groups_rounded),
          const SizedBox(height: 12),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: _roles
                .map(
                  (role) => SizedBox(
                    width: 320,
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Row(
                              children: <Widget>[
                                Icon(role.$3, color: AppColors.primary, size: 20),
                                const SizedBox(width: 8),
                                Text(
                                  role.$1,
                                  style: theme.textTheme.titleMedium,
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(
                              role.$2,
                              style: theme.textTheme.bodySmall?.copyWith(
                                height: 1.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                )
                .toList(growable: false),
          ),
          const SizedBox(height: 24),
          _sectionTitle(context, 'Normal path', Icons.route_rounded),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
              child: Column(
                children: <Widget>[
                  for (int index = 0; index < _steps.length; index++)
                    _StepTile(
                      step: _steps[index],
                      number: index + 1,
                      isLast: index == _steps.length - 1,
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          _sectionTitle(context, 'Status reference', Icons.label_rounded),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Wrap(
                spacing: 10,
                runSpacing: 10,
                children: ApplicationStatus.values
                    .map(
                      (ApplicationStatus status) => Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 7,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.surfaceAlt,
                          borderRadius: BorderRadius.circular(9),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            Container(
                              width: 8,
                              height: 8,
                              decoration: BoxDecoration(
                                color: _colorFor(status),
                                shape: BoxShape.circle,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Text(status.label, style: theme.textTheme.bodySmall),
                          ],
                        ),
                      ),
                    )
                    .toList(growable: false),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Color _colorFor(ApplicationStatus status) {
    if (status == ApplicationStatus.revisionRequested) {
      return AppColors.warning;
    }
    if (status == ApplicationStatus.rejected ||
        status == ApplicationStatus.interviewFailed) {
      return AppColors.danger;
    }
    if (status == ApplicationStatus.withdrawn) {
      return AppColors.textSecondary;
    }
    if (status == ApplicationStatus.placementConfirmed ||
        status == ApplicationStatus.interviewPassed ||
        status == ApplicationStatus.endorsed) {
      return AppColors.secondary;
    }
    return AppColors.primary;
  }
}

class _StepTile extends StatelessWidget {
  const _StepTile({
    required this.step,
    required this.number,
    required this.isLast,
  });

  final (ApplicationStatus, String, String) step;
  final int number;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Column(
            children: <Widget>[
              Container(
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.16),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: AppColors.primary.withValues(alpha: 0.6),
                  ),
                ),
                child: Text(
                  number.toString(),
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ),
              if (!isLast)
                const Expanded(
                  child: SizedBox(
                    width: 2,
                    child: ColoredBox(color: AppColors.border),
                  ),
                ),
            ],
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 12 : 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          step.$2,
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontSize: 14.5,
                          ),
                        ),
                      ),
                      Text(
                        step.$1.label,
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: AppColors.secondary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    step.$3,
                    style: theme.textTheme.bodySmall?.copyWith(height: 1.5),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}