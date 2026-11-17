import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/async_state_view.dart';
import '../../core/widgets/states/empty_state_view.dart';
import '../../models/automation_job_model.dart';
import '../../models/automation_sweep_result.dart';
import '../../models/user_model.dart';
import '../../state/auth_controller.dart';
import '../../state/automation_monitor_controller.dart';
import '../applications/widgets/summary_counter_card.dart';
import 'widgets/automation_job_card.dart';
import 'widgets/automation_weekly_summary_card.dart';
import 'widgets/requeue_job_dialog.dart';
import 'widgets/run_sweep_dialog.dart';

class AutomationMonitorScreen extends StatelessWidget {
  const AutomationMonitorScreen({super.key});

  Future<void> _runSweep(
    BuildContext context,
    AutomationMonitorController controller,
  ) async {
    final AutomationSweepSelection? selection = await showAutomationSweepDialog(
      context,
    );

    if (selection == null || !context.mounted) {
      return;
    }

    final AutomationSweepResult? result = await controller.runSweep(
      type: selection.type,
      faultMode: selection.faultMode,
    );

    if (!context.mounted) {
      return;
    }

    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);

    if (result != null) {
      messenger.showSnackBar(
        SnackBar(content: Text(controller.successMessage ?? 'Sweep finished.')),
      );
      return;
    }

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          controller.actionError?.message ??
              'The sweep could not be started. Try again.',
        ),
        backgroundColor: AppColors.danger,
        duration: const Duration(seconds: 6),
      ),
    );

    controller.clearActionError();
  }

  Future<void> _requeue(
    BuildContext context,
    AutomationMonitorController controller,
    AutomationJobModel job,
  ) async {
    final AutomationRequeueInput? input = await showAutomationRequeueDialog(
      context,
      job,
    );

    if (input == null || !context.mounted) {
      return;
    }

    final bool requeued = await controller.requeue(
      job,
      recipientIdentifier: input.recipientIdentifier,
      remarks: input.remarks,
    );

    if (!context.mounted) {
      return;
    }

    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);

    if (requeued) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            controller.successMessage ?? 'Job requeued for recovery.',
          ),
        ),
      );
      return;
    }

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          controller.actionError?.message ??
              'The job could not be requeued. Try again.',
        ),
        backgroundColor: AppColors.danger,
        duration: const Duration(seconds: 6),
      ),
    );

    controller.clearActionError();
  }

  Widget _buildEmpty(BuildContext context) {
    return EmptyStateView(
      icon: Icons.auto_mode_rounded,
      title: 'No automation jobs recorded yet',
      message:
          'Jobs appear here once a sweep is enqueued or the weekly summary is '
          'generated. The coordinator schedules these runs daily, or you can '
          'start a controlled sweep yourself.',
      actionLabel: 'Run a sweep',
      onAction: () => _runSweep(context, context.read<AutomationMonitorController>()),
      accent: AppColors.secondary,
    );
  }

  @override
  Widget build(BuildContext context) {
    final UserModel? user = context.watch<AuthController>().user;

    if (user == null) {
      return const SizedBox.shrink();
    }

    final AutomationMonitorController controller =
        context.watch<AutomationMonitorController>();
    final bool canManage = user.isCoordinator;

    final int inFlight = controller.jobs
        .where(
          (AutomationJobModel job) =>
              job.status == AutomationJobStatus.queued ||
              job.status == AutomationJobStatus.retrying,
        )
        .length;

    final int succeeded = controller.jobs
        .where(
          (AutomationJobModel job) =>
              job.status == AutomationJobStatus.succeeded,
        )
        .length;

    final int needsReview = controller.manualReviewJobs.length;

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Wrap(
              spacing: 8,
              runSpacing: 12,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: <Widget>[
                ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 520),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Text(
                        'Automation monitor',
                        style: Theme.of(context).textTheme.displaySmall?.copyWith(
                          fontSize: 22,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Bounded retries, sanitised error summaries and '
                        'coordinator recovery for every scheduled sweep.',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Refresh automation jobs',
                  onPressed: controller.loading ? null : () => controller.load(),
                  icon: controller.loading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.refresh_rounded),
                ),
                if (canManage)
                  FilledButton.icon(
                    onPressed: controller.running
                        ? null
                        : () => _runSweep(context, controller),
                    icon: controller.running
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.play_arrow_rounded, size: 18),
                    label: const Text('Run Controlled Sweep'),
                  ),
              ],
            ),
            const SizedBox(height: 18),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: <Widget>[
                SummaryCounterCard(
                  icon: Icons.auto_mode_rounded,
                  label: 'Total jobs',
                  value: controller.jobs.length,
                  accent: AppColors.primary,
                ),
                SummaryCounterCard(
                  icon: Icons.timelapse_rounded,
                  label: 'In flight',
                  value: inFlight,
                  accent: AppColors.info,
                ),
                SummaryCounterCard(
                  icon: Icons.check_circle_rounded,
                  label: 'Succeeded',
                  value: succeeded,
                  accent: AppColors.secondary,
                ),
                SummaryCounterCard(
                  icon: Icons.handyman_rounded,
                  label: 'Need review',
                  value: needsReview,
                  accent: AppColors.warning,
                ),
              ],
            ),
            if (controller.weeklySummary != null) ...<Widget>[
              const SizedBox(height: 18),
              AutomationWeeklySummaryCard(summary: controller.weeklySummary!),
            ],
            const SizedBox(height: 16),
            _AutomationStatusFilterBar(
              selected: controller.statusFilter,
              counts: controller.jobs,
              onSelected: controller.setStatusFilter,
            ),
            const SizedBox(height: 14),
            Expanded(
              child: AsyncStateView(
                loading: controller.loading && controller.jobs.isEmpty,
                loadingMessage: 'Loading automation jobs...',
                error: controller.loadError,
                isEmpty: controller.jobs.isEmpty,
                emptyBuilder: _buildEmpty,
                onRetry: () => controller.load(),
                onSessionExpired: () =>
                    context.read<AuthController>().expireSession(),
                forbiddenMessage:
                    'Automation monitoring is restricted to the coordinator '
                    'role. Sign in as the coordinator account to review jobs, '
                    'runs and recovery actions.',
                builder: (BuildContext context) {
                  return ListView.separated(
                    padding: EdgeInsets.zero,
                    itemCount: controller.jobs.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (BuildContext context, int index) {
                      final AutomationJobModel job = controller.jobs[index];

                      return AutomationJobCard(
                        job: job,
                        canManage: canManage,
                        busy: controller.requeuing,
                        onRequeue: () => _requeue(context, controller, job),
                      );
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AutomationStatusFilterBar extends StatelessWidget {
  const _AutomationStatusFilterBar({
    required this.selected,
    required this.counts,
    required this.onSelected,
  });

  final AutomationJobStatus? selected;

  final List<AutomationJobModel> counts;

  final Future<void> Function(AutomationJobStatus?) onSelected;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: <Widget>[
          ChoiceChip(
            label: const Text('All'),
            selected: selected == null,
            onSelected: (_) => onSelected(null),
          ),
          const SizedBox(width: 8),
          for (final AutomationJobStatus status in AutomationJobStatus.filterable)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                label: Text('${status.label} (${_countFor(status)})'),
                selected: selected == status,
                selectedColor: status.color.withValues(alpha: 0.18),
                labelStyle: theme.textTheme.labelLarge?.copyWith(
                  color: selected == status
                      ? status.color
                      : AppColors.textSecondary,
                ),
                onSelected: (_) => onSelected(status),
              ),
            ),
        ],
      ),
    );
  }

  int _countFor(AutomationJobStatus status) {
    return counts
        .where((AutomationJobModel job) => job.status == status)
        .length;
  }
}