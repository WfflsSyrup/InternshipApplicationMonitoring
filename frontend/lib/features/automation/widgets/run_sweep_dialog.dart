import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/automation_job_model.dart';

class AutomationSweepSelection {
  const AutomationSweepSelection({
    required this.type,
    required this.faultMode,
  });

  final AutomationSweepType type;

  final AutomationFaultMode faultMode;
}

Future<AutomationSweepSelection?> showAutomationSweepDialog(
  BuildContext context,
) {
  return showDialog<AutomationSweepSelection>(
    context: context,
    builder: (BuildContext dialogContext) {
      return const _RunSweepDialog();
    },
  );
}

class _RunSweepDialog extends StatefulWidget {
  const _RunSweepDialog();

  @override
  State<_RunSweepDialog> createState() => _RunSweepDialogState();
}

class _RunSweepDialogState extends State<_RunSweepDialog> {
  AutomationSweepType _type = AutomationSweepType.incomplete;

  AutomationFaultMode _faultMode = AutomationFaultMode.none;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return AlertDialog(
      title: const Text('Run a controlled automation sweep'),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              'Coordinator-only action. The sweep only enqueues deterministic '
              'jobs and then dispatches them through the bounded retry policy. '
              'No application status changes.',
              style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
            ),
            const SizedBox(height: 16),
            for (final AutomationSweepType option
                in AutomationSweepType.values)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Material(
                  color: _type == option
                      ? AppColors.secondary.withValues(alpha: 0.12)
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(12),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () => setState(() => _type = option),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Icon(
                            _type == option
                                ? Icons.radio_button_checked
                                : Icons.radio_button_unchecked,
                            size: 20,
                            color: _type == option
                                ? AppColors.secondary
                                : AppColors.textSecondary,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  option.label,
                                  style: theme.textTheme.titleSmall,
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  option.description,
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    height: 1.4,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  'Scheduled: ${option.scheduleLabel}',
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    color: AppColors.secondary,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            const Divider(height: 20),
            DropdownButtonFormField<AutomationFaultMode>(
              initialValue: _faultMode,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: 'Fault injection (test only)',
                prefixIcon: Icon(Icons.science_rounded),
                helperText:
                    'Force a delivery outcome so the retry policy can be '
                    'demonstrated. Leave on "None" for a real run.',
                helperMaxLines: 3,
              ),
              items: AutomationFaultMode.values
                  .map(
                    (AutomationFaultMode mode) => DropdownMenuItem<
                      AutomationFaultMode
                    >(
                      value: mode,
                      child: Text(
                        mode.label,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  )
                  .toList(growable: false),
              onChanged: (AutomationFaultMode? value) {
                if (value == null) {
                  return;
                }
                setState(() => _faultMode = value);
              },
            ),
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.warning.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: AppColors.warning.withValues(alpha: 0.35),
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  const Icon(
                    Icons.info_outline_rounded,
                    size: 17,
                    color: AppColors.warning,
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Text(
                      'Fault injection is rejected by the API unless the '
                      'automation faults are enabled in this environment.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.warning,
                        height: 1.4,
                      ),
                    ),
                  ),
                ],
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
          onPressed: () => Navigator.of(context).pop(
            AutomationSweepSelection(type: _type, faultMode: _faultMode),
          ),
          icon: const Icon(Icons.play_arrow_rounded),
          label: const Text('Run Sweep'),
        ),
      ],
    );
  }
}