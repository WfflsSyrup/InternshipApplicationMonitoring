import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/security_posture_model.dart';

class SecurityPostureCard extends StatelessWidget {
  const SecurityPostureCard({super.key, required this.posture});

  final SecurityPostureModel posture;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final int total = posture.checks.length;
    final int passed = posture.passedChecks;
    final bool allPassed = passed == total;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                const Icon(
                  Icons.verified_user_rounded,
                  size: 19,
                  color: AppColors.secondary,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Hardening posture',
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: (allPassed ? AppColors.secondary : AppColors.warning)
                        .withValues(alpha: 0.16),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '$passed / $total checks',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: allPassed ? AppColors.secondary : AppColors.warning,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: <Widget>[
                for (final SecurityCheck check in posture.checks)
                  _CheckChip(check: check),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: <Widget>[
                Icon(
                  posture.debugModeDisabled
                      ? Icons.check_circle_rounded
                      : Icons.warning_amber_rounded,
                  size: 16,
                  color: posture.debugModeDisabled
                      ? AppColors.secondary
                      : AppColors.warning,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '${posture.auditEventsTotal} security events recorded · '
                    '${posture.auditEventsLast24h} in the last 24 hours',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CheckChip extends StatelessWidget {
  const _CheckChip({required this.check});

  final SecurityCheck check;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Color accent = check.passed ? AppColors.secondary : AppColors.warning;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.surfaceAlt,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: accent.withValues(alpha: 0.35)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(
            check.passed
                ? Icons.check_circle_rounded
                : Icons.warning_amber_rounded,
            size: 16,
            color: accent,
          ),
          const SizedBox(width: 8),
          Flexible(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Text(
                  check.label,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  check.detail,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                    height: 1.35,
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