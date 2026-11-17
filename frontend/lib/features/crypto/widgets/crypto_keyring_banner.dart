import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/crypto_keyring_model.dart';

class CryptoKeyringBanner extends StatelessWidget {
  const CryptoKeyringBanner({
    super.key,
    required this.status,
    this.compact = false,
  });

  final CryptoKeyringStatus status;

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: EdgeInsets.all(compact ? 14 : 18),
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
                    Icon(
                      Icons.lock_rounded,
                      size: compact ? 17 : 19,
                      color: AppColors.secondary,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Keyring & policy',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontSize: compact ? 15 : null,
                      ),
                    ),
                  ],
                ),
                _Pill(
                  label: 'Active key ${status.activeKid}',
                  accent: AppColors.secondary,
                  icon: Icons.vpn_key_rounded,
                ),
                _Pill(
                  label: 'Replay window ${status.replayWindowLabel}',
                  accent: AppColors.info,
                  icon: Icons.timer_rounded,
                ),
                _Pill(
                  label: 'Client keys: ${status.clientKeysEmbedded} embedded',
                  accent: AppColors.textSecondary,
                  icon: Icons.phone_android_rounded,
                ),
              ],
            ),
            if (compact) ...<Widget>[
              const SizedBox(height: 10),
              Text(
                '${status.cipherSuite} · ${status.ivLabel} · ${status.tagLabel} · '
                'grace keys: ${status.supportedKidsLabel}',
                style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
              ),
            ] else ...<Widget>[
              const SizedBox(height: 14),
              Wrap(
                spacing: 20,
                runSpacing: 10,
                children: <Widget>[
                  _Stat(label: 'Cipher suite', value: status.cipherSuite),
                  _Stat(
                    label: 'Signature',
                    value: status.signatureAlgorithm,
                  ),
                  _Stat(
                    label: 'Grace keys',
                    value: status.supportedKidsLabel,
                  ),
                  _Stat(
                    label: 'IV / tag',
                    value: '${status.ivLabel} · ${status.tagLabel}',
                  ),
                  _Stat(
                    label: 'Envelopes',
                    value:
                        '${status.totals.verified} verified · '
                        '${status.totals.rejected} rejected',
                  ),
                ],
              ),
              if (!status.tamperSimulationEnabled) ...<Widget>[
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.warning.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: AppColors.warning.withValues(alpha: 0.35),
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const Icon(
                        Icons.science_outlined,
                        size: 16,
                        color: AppColors.warning,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'The verification lab is disabled for this environment.',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.warning,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
            if (status.keyMaterialExposed) ...<Widget>[
              const SizedBox(height: 10),
              _DangerNote(
                message:
                    'The keyring reports key material exposure. Rotate the '
                    'CRYPTO_KEY_* values immediately.',
                theme: theme,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({
    required this.label,
    required this.accent,
    required this.icon,
  });

  final String label;

  final Color accent;

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 13, color: accent),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: accent,
            ),
          ),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});

  final String label;

  final String value;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 260),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Text(label, style: theme.textTheme.bodySmall),
          const SizedBox(height: 2),
          Text(
            value,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w600,
              height: 1.35,
            ),
          ),
        ],
      ),
    );
  }
}

class _DangerNote extends StatelessWidget {
  const _DangerNote({required this.message, required this.theme});

  final String message;

  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.danger.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.danger.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Icon(
            Icons.warning_amber_rounded,
            size: 17,
            color: AppColors.danger,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              message,
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.danger,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}