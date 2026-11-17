import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/async_state_view.dart';
import '../../core/widgets/states/empty_state_view.dart';
import '../../models/application_model.dart';
import '../../models/application_status.dart';
import '../../models/user_model.dart';
import '../../state/application_list_controller.dart';
import '../../state/auth_controller.dart';
import 'application_detail_screen.dart';
import 'application_form_screen.dart';
import 'widgets/application_card.dart';
import 'widgets/application_table.dart';
import 'widgets/status_filter_chips.dart';
import 'widgets/summary_counter_card.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  static const double _tableBreakpoint = 980;

  Future<void> _openDetail(BuildContext context, ApplicationModel application) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => ApplicationDetailScreen(applicationId: application.id),
      ),
    );

    if (context.mounted) {
      await context.read<ApplicationListController>().load();
    }
  }

  Future<void> _openForm(BuildContext context, {ApplicationModel? existing}) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => ApplicationFormScreen(existing: existing),
      ),
    );

    if (context.mounted) {
      await context.read<ApplicationListController>().load();
    }
  }

  Widget _buildEmpty(BuildContext context) {
    final bool isStudent = context.read<AuthController>().user?.isStudent ?? false;

    return EmptyStateView(
      icon: Icons.inbox_rounded,
      title: 'No internship applications found',
      message: isStudent
          ? 'You have not created an internship application yet. Start a new '
              'application to begin the endorsement and placement workflow.'
          : 'No applications are visible for your role yet. Applications appear '
              'here once students submit them and they match your scope.',
      actionLabel: isStudent ? 'Create application' : null,
      onAction: isStudent ? () => _openForm(context) : null,
    );
  }

  Widget _buildFilteredEmpty(BuildContext context) {
    return EmptyStateView(
      icon: Icons.filter_alt_off_rounded,
      title: 'No applications in this status',
      message: 'There are no applications matching the selected status filter. '
          'Clear the filter to see every application in your scope.',
      actionLabel: 'Clear filter',
      onAction: () =>
          context.read<ApplicationListController>().setStatusFilter(null),
      accent: AppColors.primary,
    );
  }

  Widget _buildResults(BuildContext context, ApplicationListController list) {
    final List<ApplicationModel> applications = list.applications;
    final bool canEdit = context.watch<AuthController>().user?.isStudent ?? false;

    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        if (constraints.maxWidth >= _tableBreakpoint) {
          return ApplicationTable(
            applications: applications,
            onOpen: (ApplicationModel application) =>
                _openDetail(context, application),
            onEdit: canEdit
                ? (ApplicationModel application) =>
                    _openForm(context, existing: application)
                : null,
          );
        }

        return ListView.separated(
          padding: EdgeInsets.zero,
          itemCount: applications.length,
          separatorBuilder: (_, _) => const SizedBox(height: 12),
          itemBuilder: (BuildContext context, int index) {
            final ApplicationModel application = applications[index];

            return ApplicationCard(
              application: application,
              onOpen: () => _openDetail(context, application),
              onEdit: canEdit && application.canStudentEdit
                  ? () => _openForm(context, existing: application)
                  : null,
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final ApplicationListController list = context.watch<ApplicationListController>();
    final UserModel? user = context.watch<AuthController>().user;

    if (user == null) {
      return const SizedBox.shrink();
    }

    final Map<ApplicationStatus, int> counts = list.statusCounts;
    final int total = list.allApplications.length;

    final int active = list.allApplications
        .where((ApplicationModel application) => application.status.isActive)
        .length;

    final int completed = list.allApplications
        .where(
          (ApplicationModel application) =>
              application.status == ApplicationStatus.placementConfirmed,
        )
        .length;

    final int closed = list.allApplications
        .where(
          (ApplicationModel application) =>
              application.status == ApplicationStatus.withdrawn ||
              application.status == ApplicationStatus.rejected,
        )
        .length;

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
                        'Welcome back, ${user.name.split(' ').first}',
                        style: Theme.of(context).textTheme.displaySmall?.copyWith(
                          fontSize: 22,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${user.roleLabel} · '
                        '${user.departmentOrCompany ?? 'No department assigned'}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Refresh applications',
                  onPressed: list.loading ? null : () => list.load(),
                  icon: list.loading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.refresh_rounded),
                ),
                if (user.isStudent)
                  FilledButton.icon(
                    onPressed: () => _openForm(context),
                    icon: const Icon(Icons.add_rounded, size: 18),
                    label: const Text('New Application'),
                  ),
              ],
            ),
            const SizedBox(height: 18),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: <Widget>[
                SummaryCounterCard(
                  icon: Icons.folder_copy_rounded,
                  label: 'Total applications',
                  value: total,
                  accent: AppColors.primary,
                ),
                SummaryCounterCard(
                  icon: Icons.timelapse_rounded,
                  label: 'In progress',
                  value: active,
                  accent: AppColors.info,
                ),
                SummaryCounterCard(
                  icon: Icons.verified_rounded,
                  label: 'Placements confirmed',
                  value: completed,
                  accent: AppColors.secondary,
                ),
                SummaryCounterCard(
                  icon: Icons.block_rounded,
                  label: 'Closed records',
                  value: closed,
                  accent: AppColors.warning,
                ),
              ],
            ),
            const SizedBox(height: 20),
            StatusFilterChips(
              counts: counts,
              selected: list.statusFilter,
              onSelected: list.setStatusFilter,
            ),
            const SizedBox(height: 16),
            Expanded(
              child: AsyncStateView(
                loading: list.loading && list.allApplications.isEmpty,
                loadingMessage: 'Loading internship applications...',
                error: list.error,
                isEmpty: list.isEmpty,
                emptyBuilder: _buildEmpty,
                onRetry: () => list.load(),
                onSessionExpired: () =>
                    context.read<AuthController>().expireSession(),
                builder: (BuildContext context) {
                  if (list.isFilteredEmpty) {
                    return _buildFilteredEmpty(context);
                  }

                  return _buildResults(context, list);
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}