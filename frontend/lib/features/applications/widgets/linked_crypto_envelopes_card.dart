import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date_format.dart';
import '../../../core/widgets/crypto_status_badge.dart';
import '../../../models/crypto_envelope_model.dart';
import '../../../services/crypto_service.dart';

class LinkedCryptoEnvelopesCard extends StatefulWidget {
  const LinkedCryptoEnvelopesCard({super.key, required this.applicationId});

  final int applicationId;

  @override
  State<LinkedCryptoEnvelopesCard> createState() =>
      _LinkedCryptoEnvelopesCardState();
}

class _LinkedCryptoEnvelopesCardState
    extends State<LinkedCryptoEnvelopesCard> {
  late Future<List<CryptoEnvelopeModel>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<CryptoEnvelopeModel>> _load() {
    return context.read<CryptoService>().envelopes(
      applicationId: widget.applicationId,
    );
  }

  void _refresh() {
    setState(() => _future = _load());
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                const Icon(
                  Icons.lock_rounded,
                  size: 19,
                  color: AppColors.secondary,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Cryptographic envelopes',
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                IconButton(
                  tooltip: 'Refresh cryptographic envelopes',
                  onPressed: _refresh,
                  icon: const Icon(Icons.refresh_rounded, size: 20),
                ),
              ],
            ),
            const SizedBox(height: 12),
            FutureBuilder<List<CryptoEnvelopeModel>>(
              future: _future,
              builder: (
                BuildContext context,
                AsyncSnapshot<List<CryptoEnvelopeModel>> snapshot,
              ) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return Row(
                    children: <Widget>[
                      const SizedBox(
                        width: 15,
                        height: 15,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                      const SizedBox(width: 10),
                      Text(
                        'Loading cryptographic envelopes...',
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  );
                }

                if (snapshot.hasError) {
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const Icon(
                        Icons.error_outline_rounded,
                        size: 16,
                        color: AppColors.warning,
                      ),
                      const SizedBox(width: 9),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              'Cryptographic envelopes could not be loaded. The '
                              'application itself is unaffected.',
                              style: theme.textTheme.bodySmall,
                            ),
                            const SizedBox(height: 6),
                            TextButton(
                              onPressed: _refresh,
                              child: const Text('Try again'),
                            ),
                          ],
                        ),
                      ),
                    ],
                  );
                }

                final List<CryptoEnvelopeModel> envelopes =
                    snapshot.data ?? const <CryptoEnvelopeModel>[];

                if (envelopes.isEmpty) {
                  return Text(
                    'No AES-256-GCM envelope has been sealed for this '
                    'application yet. Coordinator partner packets and verified '
                    'partner callbacks appear here.',
                    style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
                  );
                }

                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    for (int index = 0; index < envelopes.length; index++) ...<
                        Widget
                      >[
                        if (index > 0) const SizedBox(height: 8),
                        _CompactEnvelopeRow(envelope: envelopes[index]),
                      ],
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _CompactEnvelopeRow extends StatelessWidget {
  const _CompactEnvelopeRow({required this.envelope});

  final CryptoEnvelopeModel envelope;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surfaceAlt,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              CryptoStatusBadge(status: envelope.status, compact: true),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  envelope.direction.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                'key ${envelope.keyId}',
                style: theme.textTheme.bodySmall,
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            '${envelope.correlationId} · ${DateFormatX.dateTime(envelope.createdAt)}',
            style: theme.textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              fontSize: 11.5,
            ),
          ),
          if ((envelope.failureReason ?? '').isNotEmpty) ...<Widget>[
            const SizedBox(height: 4),
            Text(
              envelope.failureReason!,
              style: theme.textTheme.bodySmall?.copyWith(
                color: envelope.status.color,
                height: 1.4,
              ),
            ),
          ],
        ],
      ),
    );
  }
}