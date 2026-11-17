import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date_format.dart';
import '../../../models/automation_sweep_result.dart';

class AutomationWeeklySummaryCard extends StatelessWidget {
  const AutomationWeeklySummaryCard({
    super.key,
    required this.summary,
  });

  final AutomationWeeklySummary summary;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Wrap(
              spacing: 10,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: <Widget>[
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    const Icon(
                      Icons.calendar_view_week_rounded,
                      size: 18,
                      color: AppColors.secondary,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Weekly automation summary',
                      style: theme.textTheme.titleMedium,
                    ),
                  ],
                ),
                _Chip(
                  label: 'Week ${summary.weekKey}',
                  accent: AppColors.secondary,
                ),
                _Chip(label: '${summary.total} jobs', accent: AppColors.primary),
                if (summary.generatedAt != null)
                  _Chip(
                    label: DateFormatX.dateTime(summary.generatedAt),
                    accent: AppColors.textSecondary,
                  ),
              ],
            ),
            if ((summary.weekStart ?? '').isNotEmpty) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                'Window: ${summary.weekStart} → ${summary.weekEnd ?? '—'}',
                style: theme.textTheme.bodySmall,
              ),
            ],
            if (summary.isEmpty) ...<Widget>[
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.surfaceAlt,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  'No automation jobs have completed during this week. '
                  'The coordinator schedules this summary every Monday at 07:00.',
                  style: theme.textTheme.bodySmall?.copyWith(height: 1.4),
                ),
              ),
            ] else ...<Widget>[
              const SizedBox(height: 14),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: <Widget>[
                  for (final MapEntry<String, int> entry in summary.sortedCounts)
                    _CountTile(label: entry.key, count: entry.value),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.accent});

  final String label;

  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: accent,
        ),
      ),
    );
  }
}

class _CountTile extends StatelessWidget {
  const _CountTile({required this.label, required this.count});

  final String label;

  final int count;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.surfaceAlt,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Text(
            '$count',
            style: theme.textTheme.titleLarge?.copyWith(
              color: AppColors.primary,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(width: 8),
          Text(label, style: theme.textTheme.bodySmall),
        ],
      ),
    );
  }
}