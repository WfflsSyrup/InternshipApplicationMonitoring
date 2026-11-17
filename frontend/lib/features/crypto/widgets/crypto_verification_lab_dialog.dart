import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/crypto_simulation_result.dart';

class CryptoLabInput {
  const CryptoLabInput({
    required this.mode,
    this.referenceCode,
    this.outcome,
  });

  final CryptoSimulationMode mode;

  final String? referenceCode;

  final String? outcome;
}

Future<CryptoLabInput?> showCryptoVerificationLabDialog(
  BuildContext context, {
  String? initialReferenceCode,
  CryptoKeyringPolicy? policy,
}) {
  return showDialog<CryptoLabInput>(
    context: context,
    builder: (BuildContext dialogContext) {
      return _CryptoVerificationLabDialog(
        initialReferenceCode: initialReferenceCode,
        policy: policy,
      );
    },
  );
}

class CryptoKeyringPolicy {
  const CryptoKeyringPolicy({
    required this.clockSkewSeconds,
    required this.activeKid,
  });

  final int clockSkewSeconds;

  final String activeKid;
}

class _CryptoVerificationLabDialog extends StatefulWidget {
  const _CryptoVerificationLabDialog({this.initialReferenceCode, this.policy});

  final String? initialReferenceCode;

  final CryptoKeyringPolicy? policy;

  @override
  State<_CryptoVerificationLabDialog> createState() =>
      _CryptoVerificationLabDialogState();
}

class _CryptoVerificationLabDialogState
    extends State<_CryptoVerificationLabDialog> {
  CryptoSimulationMode _mode = CryptoSimulationMode.validCallback;

  late final TextEditingController _referenceController = TextEditingController(
    text: widget.initialReferenceCode ?? '',
  );

  String _outcome = 'interview_passed';

  String? _referenceError;

  @override
  void dispose() {
    _referenceController.dispose();
    super.dispose();
  }

  void _submit() {
    final String reference = _referenceController.text.trim();

    if (reference.isNotEmpty && reference.length < 4) {
      setState(() {
        _referenceError = 'Enter a full reference code such as INT-2026-0003.';
      });
      return;
    }

    Navigator.of(context).pop(
      CryptoLabInput(
        mode: _mode,
        referenceCode: reference.isEmpty ? null : reference,
        outcome: _outcome,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final CryptoKeyringPolicy? policy = widget.policy;

    return AlertDialog(
      title: const Text('Controlled crypto verification lab'),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              'Runs one sealed callback per scenario against the live backend '
              'and reports which check accepted or rejected it. Nothing is '
              'written to the application workflow.',
              style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
            ),
            if (policy != null) ...<Widget>[
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.surfaceAlt,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  'Active key ${policy.activeKid} · '
                  'AES-256-GCM · HMAC-SHA256 · ±${policy.clockSkewSeconds}s',
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
            const SizedBox(height: 14),
            for (final CryptoSimulationMode mode
                in CryptoSimulationMode.values.where(
                  (CryptoSimulationMode candidate) => candidate.isSelectable,
                ))
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Material(
                  color: _mode == mode
                      ? AppColors.secondary.withValues(alpha: 0.12)
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(12),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () => setState(() => _mode = mode),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Icon(
                            _mode == mode
                                ? Icons.radio_button_checked
                                : Icons.radio_button_unchecked,
                            size: 20,
                            color: _mode == mode
                                ? AppColors.secondary
                                : AppColors.textSecondary,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  mode.label,
                                  style: theme.textTheme.titleSmall,
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  mode.description,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    height: 1.4,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: (mode.expectsAcceptance
                                      ? AppColors.secondary
                                      : AppColors.warning)
                                  .withValues(alpha: 0.16),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              mode.expectsAcceptance ? '200' : '4xx',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: mode.expectsAcceptance
                                    ? AppColors.secondary
                                    : AppColors.warning,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            const Divider(height: 18),
            TextField(
              controller: _referenceController,
              maxLength: 32,
              inputFormatters: <TextInputFormatter>[
                LengthLimitingTextInputFormatter(32),
              ],
              decoration: InputDecoration(
                labelText: 'Reference code (optional)',
                prefixIcon: const Icon(Icons.badge_rounded),
                helperText:
                    'Leave blank to use the first endorsed or partner-submitted '
                    'record.',
                helperMaxLines: 2,
                errorText: _referenceError,
              ),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _outcome,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Simulated interview outcome',
                prefixIcon: Icon(Icons.how_to_vote_rounded),
              ),
              items: const <DropdownMenuItem<String>>[
                DropdownMenuItem<String>(
                  value: 'interview_passed',
                  child: Text('interview_passed'),
                ),
                DropdownMenuItem<String>(
                  value: 'interview_failed',
                  child: Text('interview_failed'),
                ),
              ],
              onChanged: (String? value) {
                if (value == null) {
                  return;
                }

                setState(() => _outcome = value);
              },
            ),
          ],
        ),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        FilledButton.icon(
          onPressed: _submit,
          icon: const Icon(Icons.science_rounded),
          label: const Text('Run Verification'),
        ),
      ],
    );
  }
}