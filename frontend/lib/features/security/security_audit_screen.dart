import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/async_state_view.dart';
import '../../core/widgets/states/empty_state_view.dart';
import '../../models/security_audit_event_model.dart';
import '../../models/user_model.dart';
import '../../state/auth_controller.dart';
import '../../state/security_audit_controller.dart';
import '../applications/widgets/summary_counter_card.dart';
import 'widgets/security_audit_event_card.dart';
import 'widgets/security_posture_card.dart';

class SecurityAuditScreen extends StatelessWidget {
  const SecurityAuditScreen({super.key});

  Widget _buildEmpty(
    BuildContext context,
    SecurityAuditController controller,
  ) {
    if (!controller.canManage) {
      return const EmptyStateView(
        icon: Icons.lock_outline_rounded,
        title: 'Security telemetry is coordinator-only',
        message:
            'Only the internship coordinator can inspect the hardening posture '
            'and the security audit trail.',
        accent: AppColors.info,
      );
    }

    return EmptyStateView(
      icon: Icons.verified_user_outlined,
      title: 'No security audit events recorded yet',
      message:
          'Blocked BOLA/BFLA attempts, mass-assignment probes, throttled logins '
          'and oversized payloads appear here as soon as they happen.',
      accent: AppColors.secondary,
    );
  }

  @override
  Widget build(BuildContext context) {
    final UserModel? user = context.watch<AuthController>().user;

    if (user == null) {
      return const SizedBox.shrink();
    }

    final SecurityAuditController controller =
        context.watch<SecurityAuditController>();

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
                  constraints: const BoxConstraints(maxWidth: 560),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Text(
                        'Security hardening & audit trail',
                        style: Theme.of(context).textTheme.displaySmall?.copyWith(
                          fontSize: 22,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'OWASP API Top 10 controls for the internship monitor: '
                        'strict CORS, response hardening headers, payload caps, '
                        'tiered rate limiting and blocked access telemetry.',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Refresh security posture',
                  onPressed: controller.loading ? null : () => controller.load(),
                  icon: controller.loading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.refresh_rounded),
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (controller.posture != null)
              SecurityPostureCard(posture: controller.posture!)
            else
              const _PostureUnavailableCard(),
            const SizedBox(height: 16),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: <Widget>[
                SummaryCounterCard(
                  icon: Icons.shield_rounded,
                  label: 'Audit events',
                  value: controller.events.length,
                  accent: AppColors.primary,
                ),
                SummaryCounterCard(
                  icon: Icons.gpp_bad_rounded,
                  label: 'High severity',
                  value: controller.highSeverityEvents.length,
                  accent: AppColors.danger,
                ),
                SummaryCounterCard(
                  icon: Icons.password_rounded,
                  label: 'Login failures',
                  value: controller.loginFailedEvents.length,
                  accent: AppColors.warning,
                ),
                SummaryCounterCard(
                  icon: Icons.lock_clock_rounded,
                  label: 'Token TTL',
                  value: controller.posture?.sanctumTtlMinutes ?? 0,
                  accent: AppColors.info,
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (controller.canManage)
              _AuditFilterBar(
                eventType: controller.eventFilter,
                severity: controller.severityFilter,
                onEventType: controller.setEventFilter,
                onSeverity: controller.setSeverityFilter,
              ),
            const SizedBox(height: 14),
            Expanded(
              child: AsyncStateView(
                loading: controller.loading && controller.events.isEmpty,
                loadingMessage: 'Loading security audit trail...',
                error: controller.loadError,
                isEmpty: controller.events.isEmpty,
                emptyBuilder: (BuildContext context) =>
                    _buildEmpty(context, controller),
                onRetry: () => controller.load(),
                onSessionExpired: () =>
                    context.read<AuthController>().expireSession(),
                forbiddenMessage:
                    'Security telemetry is restricted to the internship '
                    'coordinator account.',
                builder: (BuildContext context) {
                  return ListView.separated(
                    padding: EdgeInsets.zero,
                    itemCount: controller.events.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (BuildContext context, int index) {
                      return SecurityAuditEventCard(
                        event: controller.events[index],
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

class _PostureUnavailableCard extends StatelessWidget {
  const _PostureUnavailableCard();

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Icon(
              Icons.info_outline_rounded,
              size: 18,
              color: AppColors.info,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                'The hardening posture endpoint is coordinator-only, so no '
                'posture summary can be shown for this role.',
                style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _AuditFilterBar extends StatelessWidget {
  const _AuditFilterBar({
    required this.eventType,
    required this.severity,
    required this.onEventType,
    required this.onSeverity,
  });

  final SecurityEventType? eventType;

  final SecuritySeverity? severity;

  final ValueChanged<SecurityEventType?> onEventType;

  final ValueChanged<SecuritySeverity?> onSeverity;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: <Widget>[
              ChoiceChip(
                label: const Text('All events'),
                selected: eventType == null,
                onSelected: (_) => onEventType(null),
              ),
              const SizedBox(width: 8),
              for (final SecurityEventType candidate
                  in SecurityEventType.values.where(
                    (SecurityEventType value) =>
                        value != SecurityEventType.unknown,
                  ))
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(candidate.label),
                    selected: eventType == candidate,
                    onSelected: (_) => onEventType(candidate),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: <Widget>[
              ChoiceChip(
                label: const Text('All severities'),
                selected: severity == null,
                onSelected: (_) => onSeverity(null),
              ),
              const SizedBox(width: 8),
              for (final SecuritySeverity candidate in <SecuritySeverity>[
                SecuritySeverity.info,
                SecuritySeverity.warning,
                SecuritySeverity.high,
              ])
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(candidate.label),
                    selected: severity == candidate,
                    onSelected: (_) => onSeverity(candidate),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}