import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/application_status.dart';

class StatusFilterChips extends StatelessWidget {
  const StatusFilterChips({
    super.key,
    required this.counts,
    required this.selected,
    required this.onSelected,
  });

  final Map<ApplicationStatus, int> counts;
  final ApplicationStatus? selected;
  final ValueChanged<ApplicationStatus?> onSelected;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text('Filter by status', style: theme.textTheme.labelSmall),
        const SizedBox(height: 8),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: <Widget>[
              _chip(
                label: 'All',
                count: counts.values.fold<int>(0, (int sum, int value) => sum + value),
                active: selected == null,
                accent: AppColors.primary,
                onTap: () => onSelected(null),
              ),
              const SizedBox(width: 8),
              for (final ApplicationStatus status in ApplicationStatus.valuesInWorkflow)
                _chip(
                  label: status.label,
                  count: counts[status] ?? 0,
                  active: selected == status,
                  accent: _accentFor(status),
                  onTap: () => onSelected(status),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Color _accentFor(ApplicationStatus status) {
    if (status == ApplicationStatus.interviewFailed ||
        status == ApplicationStatus.rejected) {
      return AppColors.danger;
    }
    if (status == ApplicationStatus.revisionRequested) {
      return AppColors.warning;
    }
    return AppColors.primary;
  }

  Widget _chip({
    required String label,
    required int count,
    required bool active,
    required Color accent,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        selected: active,
        onSelected: (_) => onTap(),
        avatar: Container(
          width: 20,
          height: 20,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: active
                ? AppColors.onPrimary.withValues(alpha: 0.18)
                : accent.withValues(alpha: 0.18),
            shape: BoxShape.circle,
          ),
          child: Text(
            count.toString(),
            style: TextStyle(
              fontSize: 10.5,
              fontWeight: FontWeight.w700,
              color: active ? AppColors.onPrimary : accent,
            ),
          ),
        ),
        label: Text(label),
        labelStyle: TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          color: active ? AppColors.onPrimary : AppColors.textPrimary,
        ),
      ),
    );
  }
}