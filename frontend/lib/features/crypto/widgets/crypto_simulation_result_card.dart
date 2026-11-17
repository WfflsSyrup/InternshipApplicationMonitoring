import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/crypto_simulation_result.dart';

class CryptoSimulationResultCard extends StatelessWidget {
  const CryptoSimulationResultCard({
    super.key,
    required this.result,
    this.onDismiss,
  });

  final CryptoSimulationResult result;

  final VoidCallback? onDismiss;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Color accent = result.matchedExpectation
        ? AppColors.secondary
        : AppColors.warning;

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
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    const Icon(
                      Icons.science_rounded,
                      size: 18,
                      color: AppColors.secondary,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Verification lab · ${result.mode.label}',
                      style: theme.textTheme.titleMedium,
                    ),
                  ],
                ),
                _Chip(
                  label: result.outcomeLabel,
                  accent: result.accepted
                      ? AppColors.secondary
                      : AppColors.danger,
                ),
                _Chip(label: result.expectationLabel, accent: accent),
                if (result.envelopeId != null)
                  _Chip(
                    label: 'envelope #${result.envelopeId}',
                    accent: AppColors.textSecondary,
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              result.message,
              style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
            ),
            const SizedBox(height: 6),
            Text(
              'Reference ${result.referenceCode} · outcome ${result.outcome} · '
              'key ${result.keyId} · ${result.durationMs} ms',
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
                fontSize: 11.5,
              ),
            ),
            if (result.firstAttempt != null) ...<Widget>[
              const SizedBox(height: 6),
              Text(
                'First delivery: ${result.firstAttempt!.result} · '
                'envelope #${result.firstAttempt!.detail}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: AppColors.textSecondary,
                  fontSize: 11.5,
                ),
              ),
            ],
            const SizedBox(height: 12),
            for (final CryptoSimulationStep step in result.steps)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Icon(
                      _iconFor(step.result),
                      size: 14,
                      color: _colorFor(step.result),
                    ),
                    const SizedBox(width: 8),
                    SizedBox(
                      width: 120,
                      child: Text(
                        step.name,
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    Expanded(
                      child: Text(
                        step.detail,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            if (onDismiss != null) ...<Widget>[
              const SizedBox(height: 10),
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: onDismiss,
                  icon: const Icon(Icons.close_rounded, size: 16),
                  label: const Text('Dismiss result'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  static IconData _iconFor(String result) {
    if (result.startsWith('verified') || result.startsWith('consumed')) {
      return Icons.check_circle_rounded;
    }

    if (result.startsWith('rejected') || result.startsWith('forged')) {
      return Icons.cancel_rounded;
    }

    return Icons.remove_circle_outline_rounded;
  }

  static Color _colorFor(String result) {
    if (result.startsWith('verified') || result.startsWith('consumed')) {
      return AppColors.secondary;
    }

    if (result.startsWith('rejected') || result.startsWith('forged')) {
      return AppColors.danger;
    }

    return AppColors.warning;
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