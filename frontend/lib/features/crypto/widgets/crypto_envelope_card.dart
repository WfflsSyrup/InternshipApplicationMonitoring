import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date_format.dart';
import '../../../core/widgets/crypto_status_badge.dart';
import '../../../models/crypto_envelope_model.dart';

class CryptoEnvelopeCard extends StatelessWidget {
  const CryptoEnvelopeCard({super.key, required this.envelope});

  final CryptoEnvelopeModel envelope;

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
              spacing: 8,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: <Widget>[
                CryptoStatusBadge(status: envelope.status),
                _MetaChip(icon: Icons.tag_rounded, label: '#${envelope.id}'),
                _MetaChip(
                  icon: Icons.vpn_key_rounded,
                  label: 'key ${envelope.keyId}',
                ),
                _MetaChip(
                  icon: envelope.direction == CryptoDirection.outbound
                      ? Icons.upload_file_rounded
                      : Icons.download_rounded,
                  label: envelope.direction.label,
                ),
                if (envelope.isSimulation)
                  _MetaChip(
                    icon: Icons.science_rounded,
                    label: 'Simulation',
                    accent: AppColors.info,
                  ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              envelope.applicationRef ?? 'Workspace-wide envelope',
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: 2),
            Text(
              envelope.correlationId,
              style: theme.textTheme.bodySmall?.copyWith(fontSize: 11.5),
            ),
            const SizedBox(height: 12),
            _InfoRow(
              icon: Icons.lock_rounded,
              label: 'Cipher',
              value: envelope.cipherSuite,
            ),
            _InfoRow(
              icon: Icons.fingerprint_rounded,
              label: 'Payload',
              value: envelope.shaPrefix,
            ),
            _InfoRow(
              icon: Icons.verified_rounded,
              label: 'Signature',
              value: envelope.signatureVerified
                  ? 'HMAC-SHA256 verified'
                  : 'Not verified',
              accent: envelope.signatureVerified
                  ? AppColors.secondary
                  : AppColors.danger,
            ),
            _InfoRow(
              icon: Icons.repeat_on_rounded,
              label: 'Replay',
              value: envelope.replayChecked
                  ? 'Nonce consumed'
                  : 'No nonce consumed',
              accent: envelope.replayChecked
                  ? AppColors.textSecondary
                  : AppColors.warning,
            ),
            _InfoRow(
              icon: Icons.event_rounded,
              label: 'Created',
              value: DateFormatX.dateTime(envelope.createdAt),
            ),
            if ((envelope.failureReason ?? '').isNotEmpty) ...<Widget>[
              const SizedBox(height: 10),
              _Callout(
                accent: envelope.status.color,
                icon: Icons.error_outline_rounded,
                title: 'Rejection reason',
                message: envelope.failureReason!,
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
          Text(label, style: TextStyle(fontSize: 12, color: accent)),
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
    this.accent = AppColors.textPrimary,
  });

  final IconData icon;

  final String label;

  final String value;

  final Color accent;

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
            width: 82,
            child: Text(label, style: theme.textTheme.bodySmall),
          ),
          Expanded(
            child: Text(
              value,
              style: theme.textTheme.bodySmall?.copyWith(color: accent),
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