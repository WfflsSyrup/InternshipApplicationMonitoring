import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../models/application_model.dart';
import '../../../models/application_status.dart';
import '../../../services/application_service.dart';

class CryptoDispatchInput {
  const CryptoDispatchInput({required this.applicationId, this.remarks});

  final int applicationId;

  final String? remarks;
}

Future<CryptoDispatchInput?> showDispatchPartnerPacketDialog(
  BuildContext context, {
  ApplicationModel? preselected,
}) {
  return showDialog<CryptoDispatchInput>(
    context: context,
    builder: (BuildContext dialogContext) {
      return _DispatchPartnerPacketDialog(preselected: preselected);
    },
  );
}

class _DispatchPartnerPacketDialog extends StatefulWidget {
  const _DispatchPartnerPacketDialog({this.preselected});

  final ApplicationModel? preselected;

  @override
  State<_DispatchPartnerPacketDialog> createState() =>
      _DispatchPartnerPacketDialogState();
}

class _DispatchPartnerPacketDialogState
    extends State<_DispatchPartnerPacketDialog> {
  late Future<List<ApplicationModel>> _future;

  ApplicationModel? _selected;

  final TextEditingController _remarksController = TextEditingController();

  String? _error;

  @override
  void initState() {
    super.initState();

    final ApplicationModel? preselected = widget.preselected;

    if (preselected != null) {
      _selected = preselected;
      _future = Future<List<ApplicationModel>>.value(<ApplicationModel>[
        preselected,
      ]);
      return;
    }

    _future = _load();
  }

  Future<List<ApplicationModel>> _load() {
    return context.read<ApplicationService>().list().then((
      List<ApplicationModel> applications,
    ) {
      return applications
          .where(
            (ApplicationModel application) =>
                application.status == ApplicationStatus.endorsed ||
                application.status == ApplicationStatus.partnerSubmitted,
          )
          .toList(growable: false);
    });
  }

  @override
  void dispose() {
    _remarksController.dispose();
    super.dispose();
  }

  void _submit() {
    final ApplicationModel? application = _selected;

    if (application == null) {
      setState(() {
        _error = 'Select an endorsed or partner-submitted application first.';
      });
      return;
    }

    final String remarks = _remarksController.text.trim();

    Navigator.of(context).pop(
      CryptoDispatchInput(
        applicationId: application.id,
        remarks: remarks.isEmpty ? null : remarks,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return AlertDialog(
      title: const Text('Seal & dispatch partner packet'),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              'The endorsement packet is encrypted with AES-256-GCM under the '
              'active key, bound to '
              '{reference_code}|{key_id}|outbound_partner_packet as AAD, and '
              'signed with HMAC-SHA256. Only the envelope metadata is returned '
              'by the API — the ciphertext never leaves the server.',
              style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
            ),
            const SizedBox(height: 16),
            FutureBuilder<List<ApplicationModel>>(
              future: _future,
              builder: (
                BuildContext context,
                AsyncSnapshot<List<ApplicationModel>> snapshot,
              ) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Padding(
                    padding: EdgeInsets.symmetric(vertical: 16),
                    child: Row(
                      children: <Widget>[
                        SizedBox(
                          width: 15,
                          height: 15,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        SizedBox(width: 10),
                        Text('Loading eligible applications...'),
                      ],
                    ),
                  );
                }

                final List<ApplicationModel> applications =
                    snapshot.data ?? const <ApplicationModel>[];

                if (snapshot.hasError) {
                  return Text(
                    'Applications could not be loaded: '
                    '${snapshot.error is ApiException ? (snapshot.error as ApiException).message : 'unexpected error'}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.danger,
                    ),
                  );
                }

                if (applications.isEmpty) {
                  return Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.warning.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      'No endorsed or partner-submitted application is available '
                      'to seal yet.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.warning,
                        height: 1.4,
                      ),
                    ),
                  );
                }

                return DropdownButtonFormField<int>(
                  initialValue: _selected?.id,
                  isExpanded: true,
                  decoration: InputDecoration(
                    labelText: 'Application',
                    prefixIcon: const Icon(Icons.description_rounded),
                    helperText:
                        'Only endorsed or partner-submitted records can be sealed.',
                    helperMaxLines: 2,
                    errorText: _error,
                  ),
                  items: applications
                      .map(
                        (ApplicationModel application) =>
                            DropdownMenuItem<int>(
                          value: application.id,
                          child: Text(
                            '${application.referenceCode} · ${application.hostCompanyDisplay}',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      )
                      .toList(growable: false),
                  onChanged: (int? value) {
                    if (value == null) {
                      return;
                    }

                    setState(() {
                      _selected = applications.firstWhere(
                        (ApplicationModel application) =>
                            application.id == value,
                      );
                      _error = null;
                    });
                  },
                );
              },
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _remarksController,
              maxLines: 3,
              maxLength: 1000,
              inputFormatters: <TextInputFormatter>[
                LengthLimitingTextInputFormatter(1000),
              ],
              decoration: const InputDecoration(
                labelText: 'Remarks for the partner (optional)',
                alignLabelWithHint: true,
                helperText:
                    'Stored inside the sealed payload, never in the audit list.',
                helperMaxLines: 2,
              ),
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
          icon: const Icon(Icons.lock_rounded),
          label: const Text('Seal & Dispatch'),
        ),
      ],
    );
  }
}