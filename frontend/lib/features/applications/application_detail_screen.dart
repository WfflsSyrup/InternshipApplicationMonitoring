import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/date_format.dart';
import '../../core/widgets/async_state_view.dart';
import '../../core/widgets/status_badge.dart';
import '../../models/application_action.dart';
import '../../models/application_model.dart';
import '../../models/application_status.dart';
import '../../models/status_log_model.dart';
import '../../models/user_model.dart';
import '../../services/application_service.dart';
import '../../state/application_detail_controller.dart';
import '../../state/auth_controller.dart';
import 'widgets/linked_automation_jobs_card.dart';
import 'widgets/linked_crypto_envelopes_card.dart';
import 'widgets/transition_dialog.dart';

class ApplicationDetailScreen extends StatelessWidget {
  const ApplicationDetailScreen({super.key, required this.applicationId});

  final int applicationId;

  @override
  Widget build(BuildContext context) {
    final ApplicationService service = context.read<ApplicationService>();

    return ChangeNotifierProvider<ApplicationDetailController>(
      create: (_) => ApplicationDetailController(
        service: service,
        applicationId: applicationId,
      )..load(),
      child: const _ApplicationDetailView(),
    );
  }
}

class _ApplicationDetailView extends StatelessWidget {
  const _ApplicationDetailView();

  bool _isDestructive(ApplicationAction action) {
    return action.status == ApplicationStatus.withdrawn ||
        action.status == ApplicationStatus.rejected;
  }

  Future<void> _handleAction(
    BuildContext context,
    ApplicationAction action,
  ) async {
    final ApplicationService service = context.read<ApplicationService>();
    final ApplicationDetailController controller =
        context.read<ApplicationDetailController>();

    List<UserModel> reviewers = const <UserModel>[];

    if (action.requiresPartnerReviewer) {
      try {
        reviewers = await service.partnerReviewers();
      } on Exception {
        reviewers = const <UserModel>[];
      }
    }

    if (!context.mounted) {
      return;
    }

    final TransitionInput? input = await showDialog<TransitionInput>(
      context: context,
      builder: (_) => TransitionDialog(action: action, partnerReviewers: reviewers),
    );

    if (input == null || !context.mounted) {
      return;
    }

    final bool success = await controller.performAction(
      action,
      remarks: input.remarks,
      partnerReviewerId: input.partnerReviewerId,
      interviewSchedule: input.interviewSchedule,
      interviewNotes: input.interviewNotes,
    );

    if (!context.mounted) {
      return;
    }

    final String? successMessage = controller.successMessage;
    final String? failureMessage = controller.actionError?.message;

    if (success && successMessage != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(successMessage)),
      );
      return;
    }

    if (failureMessage != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(failureMessage),
          backgroundColor: AppColors.danger,
          duration: const Duration(seconds: 6),
        ),
      );
    }
  }

  Widget _buildActionBar(
    BuildContext context,
    ApplicationModel application,
    ApplicationDetailController controller,
  ) {
    if (application.actions.isEmpty) {
      return const SizedBox.shrink();
    }

    return Material(
      color: AppColors.surface,
      elevation: 0,
      child: SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
          decoration: const BoxDecoration(
            border: Border(top: BorderSide(color: AppColors.border)),
          ),
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: application.actions.map((ApplicationAction action) {
                final bool destructive = _isDestructive(action);
                final Widget child = Text(action.label);

                final Widget button = destructive
                    ? OutlinedButton(
                        onPressed: controller.acting
                            ? null
                            : () => _handleAction(context, action),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.danger,
                          side: BorderSide(
                            color: AppColors.danger.withValues(alpha: 0.6),
                          ),
                        ),
                        child: child,
                      )
                    : FilledButton(
                        onPressed: controller.acting
                            ? null
                            : () => _handleAction(context, action),
                        child: child,
                      );

                return Padding(
                  padding: const EdgeInsets.only(right: 10),
                  child: button,
                );
              }).toList(growable: false),
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ApplicationDetailController controller =
        context.watch<ApplicationDetailController>();
    final ApplicationModel? application = controller.application;

    return Scaffold(
      appBar: AppBar(
        title: Text(application?.referenceCode ?? 'Application'),
      ),
      body: AsyncStateView(
        loading: controller.loading,
        loadingMessage: 'Loading the application record...',
        error: controller.loadError,
        onRetry: controller.load,
        onSessionExpired: () => context.read<AuthController>().expireSession(),
        builder: (BuildContext context) {
          if (application == null) {
            return const SizedBox.shrink();
          }

          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
            children: <Widget>[
              _HeaderCard(application: application),
              const SizedBox(height: 16),
              _DetailsCard(application: application),
              const SizedBox(height: 16),
              _RequirementsCard(application: application),
              const SizedBox(height: 16),
              LinkedAutomationJobsCard(applicationId: application.id),
              const SizedBox(height: 16),
              LinkedCryptoEnvelopesCard(applicationId: application.id),
              const SizedBox(height: 16),
              _AuditTrailCard(logs: application.statusLogs),
              const SizedBox(height: 8),
            ],
          );
        },
      ),
      bottomNavigationBar: application == null
          ? null
          : _buildActionBar(context, application, controller),
    );
  }
}

class _HeaderCard extends StatelessWidget {
  const _HeaderCard({required this.application});

  final ApplicationModel application;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    application.referenceCode,
                    style: theme.textTheme.bodySmall?.copyWith(
                      letterSpacing: 0.8,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                StatusBadge(status: application.status),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              application.hostCompanyDisplay,
              style: theme.textTheme.headlineSmall,
            ),
            const SizedBox(height: 4),
            Text(
              application.targetRoleDisplay,
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: <Widget>[
                _HeaderChip(
                  icon: Icons.schedule_rounded,
                  label: '${application.internshipHoursRequired} required hours',
                ),
                _HeaderChip(
                  icon: Icons.event_rounded,
                  label: 'Starts ${application.startLabel}',
                ),
                _HeaderChip(
                  icon: application.requirementsComplete
                      ? Icons.verified_rounded
                      : Icons.pending_actions_rounded,
                  label: application.requirementsSummary,
                  accent: application.requirementsComplete
                      ? AppColors.secondary
                      : AppColors.warning,
                ),
              ],
            ),
            if (application.status == ApplicationStatus.placementConfirmed) ...<Widget>[
              const SizedBox(height: 16),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: (application.isPlacementAcknowledged
                          ? AppColors.secondary
                          : AppColors.warning)
                      .withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: <Widget>[
                    Icon(
                      application.isPlacementAcknowledged
                          ? Icons.verified_rounded
                          : Icons.hourglass_bottom_rounded,
                      size: 18,
                      color: application.isPlacementAcknowledged
                          ? AppColors.secondary
                          : AppColors.warning,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Placement confirmed '
                        '${DateFormatX.dateTime(application.placementConfirmedAt)} · '
                        '${application.placementAcknowledgedLabel}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _HeaderChip extends StatelessWidget {
  const _HeaderChip({
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
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
      decoration: BoxDecoration(
        color: AppColors.surfaceAlt,
        borderRadius: BorderRadius.circular(9),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 15, color: accent),
          const SizedBox(width: 7),
          Text(label, style: TextStyle(fontSize: 12.5, color: accent)),
        ],
      ),
    );
  }
}

class _DetailsCard extends StatelessWidget {
  const _DetailsCard({required this.application});

  final ApplicationModel application;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Application details', style: theme.textTheme.titleMedium),
            const SizedBox(height: 12),
            _DetailRow(label: 'Host company', value: application.hostCompanyDisplay),
            _DetailRow(label: 'Target role', value: application.targetRoleDisplay),
            _DetailRow(
              label: 'Required hours',
              value: '${application.internshipHoursRequired} hours',
            ),
            _DetailRow(label: 'Target start date', value: application.startLabel),
            _DetailRow(
              label: 'Student',
              value: application.student == null
                  ? 'Not assigned'
                  : '${application.student!.name} '
                      '(${application.student!.studentOrEmployeeId})',
            ),
            _DetailRow(
              label: 'Adviser',
              value: application.adviser?.name ?? 'Not yet assigned',
            ),
            _DetailRow(
              label: 'Partner reviewer',
              value: application.partnerReviewer?.name ?? 'Not yet assigned',
            ),
            _DetailRow(
              label: 'Interview schedule',
              value: application.interviewLabel,
            ),
            if ((application.interviewNotes ?? '').isNotEmpty)
              _DetailRow(
                label: 'Interview notes',
                value: application.interviewNotes!,
              ),
            _DetailRow(label: 'Created', value: application.createdLabel),
            _DetailRow(label: 'Last updated', value: application.updatedLabel),
          ],
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 150,
            child: Text(label, style: theme.textTheme.bodySmall),
          ),
          Expanded(
            child: Text(
              value,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: AppColors.textPrimary,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _RequirementsCard extends StatelessWidget {
  const _RequirementsCard({required this.application});

  final ApplicationModel application;

  static const Map<String, String> _labels = <String, String>{
    'resume_ready': 'Resume prepared',
    'endorsement_letter': 'Endorsement letter',
    'parent_consent': 'Parent consent',
    'medical_clearance': 'Medical clearance',
  };

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    'Requirements checklist',
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                Text(
                  application.requirementsSummary,
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
            const SizedBox(height: 10),
            for (final MapEntry<String, String> entry in _labels.entries)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  children: <Widget>[
                    Icon(
                      (application.requirementsChecklist[entry.key] ?? false)
                          ? Icons.check_circle_rounded
                          : Icons.cancel_rounded,
                      size: 19,
                      color: (application.requirementsChecklist[entry.key] ?? false)
                          ? AppColors.secondary
                          : AppColors.danger,
                    ),
                    const SizedBox(width: 10),
                    Expanded(child: Text(entry.value)),
                    Text(
                      (application.requirementsChecklist[entry.key] ?? false)
                          ? 'Ready'
                          : 'Missing',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: (application.requirementsChecklist[entry.key] ?? false)
                            ? AppColors.secondary
                            : AppColors.danger,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _AuditTrailCard extends StatelessWidget {
  const _AuditTrailCard({required this.logs});

  final List<StatusLogModel> logs;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                const Icon(Icons.history_rounded, size: 19, color: AppColors.primary),
                const SizedBox(width: 8),
                Expanded(
                  child: Text('Audit trail', style: theme.textTheme.titleMedium),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceAlt,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    '${logs.length} events',
                    style: theme.textTheme.labelSmall,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            if (logs.isEmpty)
              Text(
                'No state changes have been recorded for this application yet.',
                style: theme.textTheme.bodySmall,
              )
            else
              for (int index = 0; index < logs.length; index++)
                _TimelineEntry(
                  log: logs[index],
                  isLast: index == logs.length - 1,
                ),
          ],
        ),
      ),
    );
  }
}

class _TimelineEntry extends StatelessWidget {
  const _TimelineEntry({required this.log, required this.isLast});

  final StatusLogModel log;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Color accent = StatusBadge.colorFor(log.toStatus);

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Column(
            children: <Widget>[
              Container(
                width: 13,
                height: 13,
                margin: const EdgeInsets.only(top: 5),
                decoration: BoxDecoration(
                  color: accent,
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.background, width: 2),
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
              padding: EdgeInsets.only(bottom: isLast ? 0 : 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          '${log.fromStatusLabel} → ${log.toStatusLabel}',
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontSize: 14,
                          ),
                        ),
                      ),
                      Text(
                        DateFormatX.dateTime(log.createdAt),
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${log.actorName ?? 'Actor #${log.actorId}'} · '
                    '${log.actorRoleLabel}',
                    style: theme.textTheme.bodySmall,
                  ),
                  if ((log.remarks ?? '').isNotEmpty) ...<Widget>[
                    const SizedBox(height: 6),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 9,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.surfaceAlt,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        log.remarks!,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textPrimary,
                          height: 1.4,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}