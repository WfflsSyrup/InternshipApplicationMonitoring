import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date_format.dart';
import '../../../models/security_audit_event_model.dart';

class SecurityAuditEventCard extends StatelessWidget {
  const SecurityAuditEventCard({super.key, required this.event});

  final SecurityAuditEventModel event;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Color severityColor = _severityColor(event.severity);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: severityColor.withValues(alpha: 0.16),
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Icon(
                    _iconFor(event.eventType),
                    size: 19,
                    color: severityColor,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              event.eventLabel,
                              style: theme.textTheme.titleSmall,
                            ),
                          ),
                          const SizedBox(width: 8),
                          _SeverityBadge(severity: event.severity, color: severityColor),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${event.endpoint} · HTTP ${event.httpStatus}',
                        style: theme.textTheme.bodySmall?.copyWith(
                          fontFamily: 'monospace',
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 14,
              runSpacing: 6,
              children: <Widget>[
                _MetaItem(
                  icon: Icons.person_outline_rounded,
                  label: event.actorRole ?? 'unauthenticated',
                ),
                _MetaItem(
                  icon: Icons.fingerprint_rounded,
                  label: 'ip ${event.ipHashPrefix.isEmpty ? '—' : event.ipHashPrefix}',
                ),
                _MetaItem(
                  icon: Icons.schedule_rounded,
                  label: DateFormatX.dateTime(event.createdAt),
                ),
                _MetaItem(
                  icon: Icons.tag_rounded,
                  label: 'corr ${event.shortCorrelationId}',
                ),
              ],
            ),
            if ((event.details ?? const <String, dynamic>{}).isNotEmpty) ...<Widget>[
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.surfaceAlt,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  _formatDetails(event.details!),
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontFamily: 'monospace',
                    color: AppColors.textSecondary,
                    height: 1.4,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  static String _formatDetails(Map<String, dynamic> details) {
    final List<String> parts = <String>[];

    details.forEach((String key, dynamic value) {
      final String rendered = value is List
          ? value.join(', ')
          : value?.toString() ?? '';

      parts.add('$key: $rendered');
    });

    return parts.join(' · ');
  }

  static Color _severityColor(SecuritySeverity severity) {
    return switch (severity) {
      SecuritySeverity.high => AppColors.danger,
      SecuritySeverity.warning => AppColors.warning,
      SecuritySeverity.info => AppColors.info,
      SecuritySeverity.unknown => AppColors.textSecondary,
    };
  }

  static IconData _iconFor(SecurityEventType type) {
    return switch (type) {
      SecurityEventType.bolaBlocked => Icons.person_off_rounded,
      SecurityEventType.bflaBlocked => Icons.admin_panel_settings_rounded,
      SecurityEventType.rateLimitExceeded => Icons.speed_rounded,
      SecurityEventType.massAssignmentBlocked => Icons.rule_rounded,
      SecurityEventType.payloadTooLarge => Icons.archive_outlined,
      SecurityEventType.loginFailed => Icons.password_rounded,
      SecurityEventType.sessionRevoked => Icons.logout_rounded,
      SecurityEventType.unknown => Icons.security_rounded,
    };
  }
}

class _SeverityBadge extends StatelessWidget {
  const _SeverityBadge({required this.severity, required this.color});

  final SecuritySeverity severity;

  final Color color;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        severity.label.toUpperCase(),
        style: theme.textTheme.bodySmall?.copyWith(
          color: color,
          fontWeight: FontWeight.w700,
          fontSize: 10.5,
        ),
      ),
    );
  }
}

class _MetaItem extends StatelessWidget {
  const _MetaItem({required this.icon, required this.label});

  final IconData icon;

  final String label;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 14, color: AppColors.textSecondary),
        const SizedBox(width: 5),
        Text(
          label,
          style: theme.textTheme.bodySmall?.copyWith(
            color: AppColors.textSecondary,
          ),
        ),
      ],
    );
  }
}