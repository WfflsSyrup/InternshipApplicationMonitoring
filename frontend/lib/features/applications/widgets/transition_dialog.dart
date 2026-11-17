import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date_format.dart';
import '../../../models/application_action.dart';
import '../../../models/application_status.dart';
import '../../../models/user_model.dart';

class TransitionInput {
  const TransitionInput({
    this.remarks,
    this.partnerReviewerId,
    this.interviewSchedule,
    this.interviewNotes,
  });

  final String? remarks;
  final int? partnerReviewerId;
  final String? interviewSchedule;
  final String? interviewNotes;
}

class TransitionDialog extends StatefulWidget {
  const TransitionDialog({
    super.key,
    required this.action,
    this.partnerReviewers = const <UserModel>[],
  });

  final ApplicationAction action;
  final List<UserModel> partnerReviewers;

  @override
  State<TransitionDialog> createState() => _TransitionDialogState();
}

class _TransitionDialogState extends State<TransitionDialog> {
  final TextEditingController _remarksController = TextEditingController();

  final TextEditingController _notesController = TextEditingController();

  int? _partnerReviewerId;

  DateTime? _scheduleDate;

  TimeOfDay? _scheduleTime;

  String? _remarksError;

  String? _scheduleError;

  String? _reviewerError;

  bool get _requiresReviewer => widget.action.requiresPartnerReviewer;

  bool get _requiresSchedule => widget.action.requiresInterviewSchedule;

  bool get _requiresRemarks => widget.action.requiresRemarks;

  bool get _requiresNotes =>
      widget.action.status == ApplicationStatus.interviewPassed ||
      widget.action.status == ApplicationStatus.interviewFailed;

  @override
  void dispose() {
    _remarksController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final DateTime now = DateTime.now();

    final DateTime? date = await showDatePicker(
      context: context,
      initialDate: _scheduleDate ?? now.add(const Duration(days: 7)),
      firstDate: now.subtract(const Duration(days: 1)),
      lastDate: DateTime(now.year + 3),
      helpText: 'Select the interview date',
    );

    if (date == null || !mounted) {
      return;
    }

    final TimeOfDay? time = await showTimePicker(
      context: context,
      initialTime: _scheduleTime ?? const TimeOfDay(hour: 9, minute: 0),
      helpText: 'Select the interview time',
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _scheduleDate = date;
      if (time != null) {
        _scheduleTime = time;
      }
      _scheduleError = null;
    });
  }

  String get _scheduleLabel {
    final DateTime? date = _scheduleDate;

    if (date == null) {
      return 'Select date and time';
    }

    final TimeOfDay time = _scheduleTime ?? const TimeOfDay(hour: 9, minute: 0);

    return '${DateFormatX.date(date.toIso8601String())} Â· '
        '${time.hour.toString().padLeft(2, '0')}:'
        '${time.minute.toString().padLeft(2, '0')}';
  }

  String? _buildSchedulePayload() {
    final DateTime? date = _scheduleDate;

    if (date == null) {
      return null;
    }

    final TimeOfDay time = _scheduleTime ?? const TimeOfDay(hour: 9, minute: 0);

    return '${DateFormatX.dateInputValue(date.toIso8601String())} '
        '${time.hour.toString().padLeft(2, '0')}:'
        '${time.minute.toString().padLeft(2, '0')}:00';
  }

  void _submit() {
    final String remarks = _remarksController.text.trim();
    final String notes = _notesController.text.trim();

    setState(() {
      _remarksError = _requiresRemarks && remarks.isEmpty
          ? 'Remarks are required for this action.'
          : null;

      _reviewerError = _requiresReviewer && _partnerReviewerId == null
          ? 'Select a partner reviewer to continue.'
          : null;

      _scheduleError = _requiresSchedule && _scheduleDate == null
          ? 'Select the interview date and time.'
          : null;
    });

    if (_remarksError != null || _reviewerError != null || _scheduleError != null) {
      return;
    }

    Navigator.of(context).pop(
      TransitionInput(
        remarks: remarks.isEmpty ? null : remarks,
        partnerReviewerId: _partnerReviewerId,
        interviewSchedule: _buildSchedulePayload(),
        interviewNotes: notes.isEmpty ? null : notes,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return AlertDialog(
      title: Text(widget.action.label),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              'Move this application to "${widget.action.status.label}". '
              'Every state change is recorded in the audit trail.',
              style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
            ),
            const SizedBox(height: 18),
            if (_requiresReviewer) ...<Widget>[
              DropdownButtonFormField<int>(
                initialValue: _partnerReviewerId,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: 'Partner reviewer *',
                  prefixIcon: const Icon(Icons.person_search_rounded),
                  errorText: _reviewerError,
                ),
                items: widget.partnerReviewers
                    .map(
                      (UserModel reviewer) => DropdownMenuItem<int>(
                        value: reviewer.id,
                        child: Text(
                          '${reviewer.name} Â· ${reviewer.departmentOrCompany ?? reviewer.email}',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    )
                    .toList(growable: false),
                onChanged: (int? value) {
                  setState(() {
                    _partnerReviewerId = value;
                    _reviewerError = null;
                  });
                },
              ),
              if (widget.partnerReviewers.isEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    'No partner reviewer accounts are available. Ask the '
                    'coordinator to provision one before continuing.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.warning,
                    ),
                  ),
                ),
              const SizedBox(height: 16),
            ],
            if (_requiresSchedule) ...<Widget>[
              InkWell(
                onTap: _pickDate,
                borderRadius: BorderRadius.circular(12),
                child: InputDecorator(
                  decoration: InputDecoration(
                    labelText: 'Interview schedule *',
                    prefixIcon: const Icon(Icons.event_rounded),
                    errorText: _scheduleError,
                  ),
                  child: Text(
                    _scheduleLabel,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      color: _scheduleDate == null
                          ? AppColors.textSecondary
                          : AppColors.textPrimary,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
            ],
            if (_requiresNotes) ...<Widget>[
              TextField(
                controller: _notesController,
                maxLines: 3,
                maxLength: 500,
                decoration: const InputDecoration(
                  labelText: 'Interview outcome notes (optional)',
                  alignLabelWithHint: true,
                ),
              ),
              const SizedBox(height: 10),
            ],
            TextField(
              controller: _remarksController,
              maxLines: 3,
              maxLength: 1000,
              inputFormatters: <TextInputFormatter>[
                LengthLimitingTextInputFormatter(1000),
              ],
              decoration: InputDecoration(
                labelText: _requiresRemarks
                    ? 'Remarks *'
                    : 'Remarks (optional)',
                alignLabelWithHint: true,
                helperText: _requiresRemarks
                    ? 'Explain what the applicant should revise or why the application is rejected.'
                    : 'Stored in the audit trail for this transition.',
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
        FilledButton(
          onPressed: _submit,
          child: const Text('Confirm'),
        ),
      ],
    );
  }
}