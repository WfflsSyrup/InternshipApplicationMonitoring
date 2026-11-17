import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date_format.dart';
import '../../../models/automation_job_model.dart';

class AutomationRequeueInput {
  const AutomationRequeueInput({
    this.recipientIdentifier,
    this.remarks,
  });

  final String? recipientIdentifier;

  final String? remarks;
}

Future<AutomationRequeueInput?> showAutomationRequeueDialog(
  BuildContext context,
  AutomationJobModel job,
) {
  return showDialog<AutomationRequeueInput>(
    context: context,
    builder: (BuildContext dialogContext) {
      return _RequeueJobDialog(job: job);
    },
  );
}

class _RequeueJobDialog extends StatefulWidget {
  const _RequeueJobDialog({required this.job});

  final AutomationJobModel job;

  @override
  State<_RequeueJobDialog> createState() => _RequeueJobDialogState();
}

class _RequeueJobDialogState extends State<_RequeueJobDialog> {
  late final TextEditingController _recipientController = TextEditingController(
    text: widget.job.recipientIdentifier ?? '',
  );

  final TextEditingController _remarksController = TextEditingController();

  String? _remarksError;

  @override
  void dispose() {
    _recipientController.dispose();
    _remarksController.dispose();
    super.dispose();
  }

  void _submit() {
    final String remarks = _remarksController.text.trim();

    setState(() {
      _remarksError = remarks.isEmpty
          ? 'Add a short remark so the recovery is traceable in the audit trail.'
          : null;
    });

    if (_remarksError != null) {
      return;
    }

    final String recipient = _recipientController.text.trim();

    Navigator.of(context).pop(
      AutomationRequeueInput(
        recipientIdentifier: recipient.isEmpty ? null : recipient,
        remarks: remarks,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AutomationJobModel job = widget.job;

    return AlertDialog(
      title: const Text('Requeue for recovery'),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.surfaceAlt,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    'Job #${job.id} · ${job.automationTypeLabel}',
                    style: theme.textTheme.titleSmall,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    job.applicationReferenceCode ?? 'No linked application',
                    style: theme.textTheme.bodySmall,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Attempts ${job.attempts}/${job.maxAttempts} · '
                    '${job.failureLabel} failure',
                    style: theme.textTheme.bodySmall,
                  ),
                  if ((job.lastErrorSummary ?? '').isNotEmpty) ...<Widget>[
                    const SizedBox(height: 6),
                    Text(
                      'Last error (HTTP ${job.lastErrorCode ?? '—'}): '
                      '${job.lastErrorSummary}',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.danger,
                      ),
                    ),
                  ],
                  if (job.nextRetryAt != null) ...<Widget>[
                    const SizedBox(height: 6),
                    Text(
                      'Next retry: ${DateFormatX.dateTime(job.nextRetryAt)}',
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Requeue resets the retry budget for this job and re-dispatches it '
              'with a fresh correlation id. The original attempt history stays '
              'in the audit trail.',
              style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _recipientController,
              decoration: const InputDecoration(
                labelText: 'Override recipient identifier (optional)',
                prefixIcon: Icon(Icons.alternate_email_rounded),
                helperText:
                    'Usually the employee or student number. Leave blank to keep '
                    'the resolved recipient.',
                helperMaxLines: 2,
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _remarksController,
              maxLines: 3,
              maxLength: 500,
              inputFormatters: <TextInputFormatter>[
                LengthLimitingTextInputFormatter(500),
              ],
              decoration: InputDecoration(
                labelText: 'Remarks *',
                alignLabelWithHint: true,
                helperText:
                    'Explain why the job is being requeued (for example, the '
                    'recipient changed after a corrected record).',
                helperMaxLines: 2,
                errorText: _remarksError,
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
          icon: const Icon(Icons.autorenew_rounded),
          label: const Text('Requeue'),
        ),
      ],
    );
  }
}