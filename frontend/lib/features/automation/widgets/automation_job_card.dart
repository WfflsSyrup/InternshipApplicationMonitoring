import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date_format.dart';
import '../../../core/widgets/automation_job_status_badge.dart';
import '../../../models/automation_job_model.dart';

class AutomationJobCard extends StatelessWidget {
  const AutomationJobCard({
    super.key,
    required this.job,
    this.onRequeue,
    this.canManage = false,
    this.busy = false,
  });

  final AutomationJobModel job;

  final VoidCallback? onRequeue;

  final bool canManage;

  final bool busy;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool needsRecovery = job.status.needsCoordinatorRecovery;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Wrap(
              spacing: 8,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: <Widget>[
                AutomationJobStatusBadge(status: job.status),
                _MetaChip(
                  icon: Icons.tag_rounded,
                  label: '#${job.id}',
                ),
                _MetaChip(
                  icon: Icons.repeat_rounded,
                  label: job.attemptsLabel,
                ),
                _MetaChip(
                  icon: Icons.fingerprint_rounded,
                  label: job.shortCorrelationId,
                ),
                _MetaChip(
                  icon: Icons.key_rounded,
                  label: job.failureLabel,
                  accent: job.isPermanentFailure
                      ? AppColors.danger
                      : AppColors.textSecondary,
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(job.automationTypeLabel, style: theme.textTheme.titleMedium),
            const SizedBox(height: 2),
            Text(
              job.applicationReferenceCode ??
                  'Workspace-wide summary (no linked application)',
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: 12),
            _InfoRow(
              icon: Icons.alternate_email_rounded,
              label: 'Recipient',
              value: job.recipientLabel,
            ),
            _InfoRow(
              icon: Icons.event_rounded,
              label: job.status == AutomationJobStatus.succeeded
                  ? 'Completed'
                  : 'Next retry',
              value: job.status == AutomationJobStatus.succeeded
                  ? DateFormatX.dateTime(job.completedAt)
                  : job.nextRetryAt == null
                  ? 'Not scheduled'
                  : DateFormatX.dateTime(job.nextRetryAt),
            ),
            if ((job.lastErrorSummary ?? '').isNotEmpty) ...<Widget>[
              const SizedBox(height: 10),
              _Callout(
                accent: job.isPermanentFailure
                    ? AppColors.danger
                    : AppColors.warning,
                icon: Icons.error_outline_rounded,
                title: job.lastErrorCode == null
                    ? 'Dispatcher error'
                    : 'Dispatcher error · HTTP ${job.lastErrorCode}',
                message: job.lastErrorSummary!,
              ),
            ],
            if (needsRecovery) ...<Widget>[
              const SizedBox(height: 10),
              _Callout(
                accent: AppColors.danger,
                icon: Icons.handyman_rounded,
                title: 'Coordinator action required',
                message: job.guidance,
              ),
            ],
            if ((job.coordinatorRemarks ?? '').isNotEmpty) ...<Widget>[
              const SizedBox(height: 10),
              _Callout(
                accent: AppColors.primary,
                icon: Icons.sticky_note_2_rounded,
                title: 'Coordinator remark',
                message: job.coordinatorRemarks!,
              ),
            ],
            if (canManage && needsRecovery) ...<Widget>[
              const SizedBox(height: 14),
              Align(
                alignment: Alignment.centerLeft,
                child: FilledButton.icon(
                  onPressed: busy ? null : onRequeue,
                  icon: busy
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.autorenew_rounded, size: 18),
                  label: const Text('Requeue for Recovery'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({
    required this.icon,
    required this.label,
    this.accent = AppColors.textSecondary,
  });

  final IconData icon;

  final String label;

  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: AppColors.surfaceAlt,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 14, color: accent),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(fontSize: 12, color: accent),
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;

  final String label;

  final String value;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, size: 15, color: AppColors.textSecondary),
          const SizedBox(width: 8),
          SizedBox(
            width: 88,
            child: Text(label, style: theme.textTheme.bodySmall),
          ),
          Expanded(
            child: Text(
              value,
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.textPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Callout extends StatelessWidget {
  const _Callout({
    required this.accent,
    required this.icon,
    required this.title,
    required this.message,
  });

  final Color accent;

  final IconData icon;

  final String title;

  final String message;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: accent.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, size: 17, color: accent),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: theme.textTheme.labelLarge?.copyWith(
                    fontSize: 12.5,
                    color: accent,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  message,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textPrimary,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}