import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/async_state_view.dart';
import '../../core/widgets/states/empty_state_view.dart';
import '../../models/crypto_envelope_model.dart';
import '../../models/crypto_keyring_model.dart';
import '../../models/crypto_simulation_result.dart';
import '../../models/user_model.dart';
import '../../state/auth_controller.dart';
import '../../state/crypto_integration_controller.dart';
import '../applications/widgets/summary_counter_card.dart';
import 'crypto_error_text.dart';
import 'widgets/crypto_envelope_card.dart';
import 'widgets/crypto_keyring_banner.dart';
import 'widgets/crypto_simulation_result_card.dart';
import 'widgets/crypto_verification_lab_dialog.dart';
import 'widgets/dispatch_partner_packet_dialog.dart';

class CryptoIntegrationScreen extends StatelessWidget {
  const CryptoIntegrationScreen({super.key});

  Future<void> _dispatch(
    BuildContext context,
    CryptoIntegrationController controller,
  ) async {
    final CryptoDispatchInput? input = await showDispatchPartnerPacketDialog(
      context,
    );

    if (input == null || !context.mounted) {
      return;
    }

    final CryptoEnvelopeModel? envelope = await controller.dispatchPacket(
      applicationId: input.applicationId,
      remarks: input.remarks,
    );

    if (!context.mounted) {
      return;
    }

    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);

    if (envelope != null) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            controller.successMessage ??
                'Partner packet sealed with AES-256-GCM.',
          ),
        ),
      );
      return;
    }

    final ApiException? failure = controller.actionError;

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          failure == null
              ? 'The partner packet could not be sealed. Try again.'
              : describeCryptoError(failure),
        ),
        backgroundColor: AppColors.danger,
        duration: const Duration(seconds: 7),
      ),
    );

    controller.clearActionError();
  }

  Future<void> _runLab(
    BuildContext context,
    CryptoIntegrationController controller,
  ) async {
    final CryptoKeyringStatus? keyring = controller.keyring;

    final CryptoLabInput? input = await showCryptoVerificationLabDialog(
      context,
      policy: keyring == null
          ? null
          : CryptoKeyringPolicy(
              clockSkewSeconds: keyring.clockSkewSeconds,
              activeKid: keyring.activeKid,
            ),
    );

    if (input == null || !context.mounted) {
      return;
    }

    final CryptoSimulationResult? result = await controller.runSimulation(
      mode: input.mode,
      referenceCode: input.referenceCode,
      outcome: input.outcome,
    );

    if (!context.mounted) {
      return;
    }

    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);

    if (result != null) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            '${result.mode.label} · ${result.outcomeLabel} · '
            '${result.expectationLabel}',
          ),
        ),
      );
      return;
    }

    final ApiException? failure = controller.actionError;

    messenger.showSnackBar(
      SnackBar(
        content: Text(
          failure == null
              ? 'The verification lab could not run. Try again.'
              : describeCryptoError(failure),
        ),
        backgroundColor: AppColors.danger,
        duration: const Duration(seconds: 7),
      ),
    );

    controller.clearActionError();
  }

  Widget _buildEmpty(
    BuildContext context,
    CryptoIntegrationController controller,
  ) {
    if (!controller.canManage) {
      return const EmptyStateView(
        icon: Icons.lock_outline_rounded,
        title: 'No cryptographic envelopes to show',
        message:
            'Sealed partner packets and verified partner callbacks appear here '
            'once the coordinator dispatches one.',
        accent: AppColors.secondary,
      );
    }

    return EmptyStateView(
      icon: Icons.lock_outline_rounded,
      title: 'No cryptographic envelopes recorded yet',
      message:
          'Seal an endorsement packet to create the first AES-256-GCM envelope, '
          'then use the verification lab to confirm the rejection paths.',
      actionLabel: 'Seal & Dispatch',
      onAction: () => _dispatch(context, controller),
      accent: AppColors.secondary,
    );
  }

  @override
  Widget build(BuildContext context) {
    final UserModel? user = context.watch<AuthController>().user;

    if (user == null) {
      return const SizedBox.shrink();
    }

    final CryptoIntegrationController controller =
        context.watch<CryptoIntegrationController>();
    final bool canManage = user.isCoordinator;

    final int rejected = controller.rejectedEnvelopes.length;

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
                        'Secure integration & crypto audit',
                        style: Theme.of(context).textTheme.displaySmall?.copyWith(
                          fontSize: 22,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'AES-256-GCM envelopes with AAD binding, detached '
                        'HMAC-SHA256 signatures and replay protection for '
                        'partner callbacks.',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Refresh crypto audit trail',
                  onPressed: controller.loading ? null : () => controller.load(),
                  icon: controller.loading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.refresh_rounded),
                ),
                if (canManage) ...<Widget>[
                  FilledButton.tonalIcon(
                    onPressed: controller.runningSimulation
                        ? null
                        : () => _runLab(context, controller),
                    icon: controller.runningSimulation
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.science_rounded, size: 18),
                    label: const Text('Verification Lab'),
                  ),
                  FilledButton.icon(
                    onPressed: controller.dispatching
                        ? null
                        : () => _dispatch(context, controller),
                    icon: controller.dispatching
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.lock_rounded, size: 18),
                    label: const Text('Seal & Dispatch'),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 18),
            if (controller.keyring != null)
              CryptoKeyringBanner(status: controller.keyring!)
            else if (canManage)
              const _KeyringUnavailableCard()
            else
              const _ReadOnlyNoticeCard(),
            const SizedBox(height: 16),
            if (controller.lastSimulation != null) ...<Widget>[
              CryptoSimulationResultCard(
                result: controller.lastSimulation!,
                onDismiss: controller.clearSimulation,
              ),
              const SizedBox(height: 16),
            ],
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: <Widget>[
                SummaryCounterCard(
                  icon: Icons.folder_rounded,
                  label: 'Total envelopes',
                  value: controller.envelopes.length,
                  accent: AppColors.primary,
                ),
                SummaryCounterCard(
                  icon: Icons.verified_user_rounded,
                  label: 'Verified',
                  value: controller.verifiedCount,
                  accent: AppColors.secondary,
                ),
                SummaryCounterCard(
                  icon: Icons.gpp_bad_rounded,
                  label: 'Rejected',
                  value: rejected,
                  accent: AppColors.warning,
                ),
                _ActiveKeyCard(
                  keyId: controller.keyring?.activeKid,
                  accent: AppColors.info,
                ),
              ],
            ),
            const SizedBox(height: 16),
            _CryptoFilterBar(
              status: controller.statusFilter,
              direction: controller.directionFilter,
              onStatus: controller.setStatusFilter,
              onDirection: controller.setDirectionFilter,
            ),
            const SizedBox(height: 14),
            Expanded(
              child: AsyncStateView(
                loading: controller.loading && controller.envelopes.isEmpty,
                loadingMessage: 'Loading cryptographic envelopes...',
                error: controller.loadError,
                isEmpty: controller.envelopes.isEmpty,
                emptyBuilder: (BuildContext context) =>
                    _buildEmpty(context, controller),
                onRetry: () => controller.load(),
                onSessionExpired: () =>
                    context.read<AuthController>().expireSession(),
                forbiddenMessage:
                    'This view is read-only for your role. Sealing partner '
                    'packets and the verification lab require the internship '
                    'coordinator account.',
                builder: (BuildContext context) {
                  return ListView.separated(
                    padding: EdgeInsets.zero,
                    itemCount: controller.envelopes.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (BuildContext context, int index) {
                      return CryptoEnvelopeCard(
                        envelope: controller.envelopes[index],
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

class _ActiveKeyCard extends StatelessWidget {
  const _ActiveKeyCard({required this.keyId, required this.accent});

  final String? keyId;

  final Color accent;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.16),
                borderRadius: BorderRadius.circular(11),
              ),
              child: Icon(Icons.vpn_key_rounded, size: 20, color: accent),
            ),
            const SizedBox(width: 12),
            Flexible(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  Text(
                    keyId ?? '—',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text('Active signing key', style: theme.textTheme.bodySmall),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _KeyringUnavailableCard extends StatelessWidget {
  const _KeyringUnavailableCard();

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
              Icons.warning_amber_rounded,
              size: 18,
              color: AppColors.warning,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                'The keyring status endpoint is restricted to the internship '
                'coordinator, so no policy banner can be shown for this role. '
                'Envelope metadata is still visible below.',
                style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ReadOnlyNoticeCard extends StatelessWidget {
  const _ReadOnlyNoticeCard();

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
              Icons.visibility_rounded,
              size: 18,
              color: AppColors.info,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                'Read-only view. The internship coordinator can seal partner '
                'packets, run the verification lab and read the keyring policy; '
                'other roles only see the envelopes linked to their own '
                'applications.',
                style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CryptoFilterBar extends StatelessWidget {
  const _CryptoFilterBar({
    required this.status,
    required this.direction,
    required this.onStatus,
    required this.onDirection,
  });

  final CryptoVerificationStatus? status;

  final CryptoDirection direction;

  final ValueChanged<CryptoVerificationStatus?> onStatus;

  final ValueChanged<CryptoDirection> onDirection;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: <Widget>[
              ChoiceChip(
                label: const Text('All'),
                selected: status == null,
                onSelected: (_) => onStatus(null),
              ),
              const SizedBox(width: 8),
              for (final CryptoVerificationStatus candidate
                  in CryptoVerificationStatus.values.where(
                    (CryptoVerificationStatus value) =>
                        value != CryptoVerificationStatus.unknown,
                  ))
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(candidate.label),
                    selected: status == candidate,
                    selectedColor: candidate.color.withValues(alpha: 0.18),
                    labelStyle: theme.textTheme.labelLarge?.copyWith(
                      color: status == candidate
                          ? candidate.color
                          : AppColors.textSecondary,
                    ),
                    onSelected: (_) => onStatus(candidate),
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
                label: const Text('All directions'),
                selected: direction == CryptoDirection.unknown,
                onSelected: (_) => onDirection(CryptoDirection.unknown),
              ),
              const SizedBox(width: 8),
              for (final CryptoDirection candidate in <CryptoDirection>[
                CryptoDirection.outbound,
                CryptoDirection.inbound,
              ])
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(candidate.label),
                    selected: direction == candidate,
                    onSelected: (_) => onDirection(candidate),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}