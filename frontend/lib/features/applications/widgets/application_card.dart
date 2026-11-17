import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../models/application_model.dart';

class ApplicationCard extends StatelessWidget {
  const ApplicationCard({
    super.key,
    required this.application,
    required this.onOpen,
    this.onEdit,
  });

  final ApplicationModel application;
  final VoidCallback onOpen;
  final VoidCallback? onEdit;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: InkWell(
        onTap: onOpen,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      application.referenceCode,
                      style: theme.textTheme.bodySmall?.copyWith(
                        letterSpacing: 0.6,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  StatusBadge(status: application.status),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                application.hostCompanyDisplay,
                style: theme.textTheme.titleMedium,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 3),
              Text(application.targetRoleDisplay, style: theme.textTheme.bodyMedium),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  _Meta(icon: Icons.schedule_rounded, label: '${application.internshipHoursRequired} hrs'),
                  _Meta(icon: Icons.event_rounded, label: application.startLabel),
                  _Meta(
                    icon: application.requirementsComplete
                        ? Icons.task_alt_rounded
                        : Icons.assignment_late_rounded,
                    label: application.requirementsSummary,
                    accent: application.requirementsComplete
                        ? AppColors.secondary
                        : AppColors.warning,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      application.student?.name ?? 'Applicant',
                      style: theme.textTheme.bodySmall,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (onEdit != null)
                    TextButton.icon(
                      onPressed: onEdit,
                      icon: const Icon(Icons.edit_outlined, size: 17),
                      label: const Text('Edit'),
                    )
                  else
                    TextButton.icon(
                      onPressed: onOpen,
                      icon: const Icon(Icons.chevron_right_rounded, size: 18),
                      label: const Text('Details'),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.label, this.accent});

  final IconData icon;
  final String label;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final Color color = accent ?? AppColors.textSecondary;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.surfaceAlt,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 6),
          Text(label, style: TextStyle(fontSize: 12, color: color)),
        ],
      ),
    );
  }
}