import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/utils/date_format.dart';
import '../../models/application_model.dart';
import '../../models/application_status.dart';
import '../../services/application_service.dart';
import '../../state/application_form_controller.dart';

class ApplicationFormScreen extends StatelessWidget {
  const ApplicationFormScreen({super.key, this.existing, this.embedded = false});

  final ApplicationModel? existing;
  final bool embedded;

  @override
  Widget build(BuildContext context) {
    final ApplicationService service = context.read<ApplicationService>();

    return ChangeNotifierProvider<ApplicationFormController>(
      create: (_) => ApplicationFormController(
        service: service,
        existing: existing,
      )..initialise(),
      child: _ApplicationFormView(existing: existing, embedded: embedded),
    );
  }
}

class _ApplicationFormView extends StatelessWidget {
  const _ApplicationFormView({required this.existing, required this.embedded});

  final ApplicationModel? existing;
  final bool embedded;

  static const Map<String, String> _checklistLabels = <String, String>{
    'resume_ready': 'Resume ready',
    'endorsement_letter': 'Endorsement letter',
    'parent_consent': 'Parent consent',
    'medical_clearance': 'Medical clearance',
  };

  Future<void> _pickStartDate(
    BuildContext context,
    ApplicationFormController controller,
  ) async {
    final DateTime now = DateTime.now();

    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: controller.startDate ?? now.add(const Duration(days: 30)),
      firstDate: DateTime(now.year - 1),
      lastDate: DateTime(now.year + 5),
      helpText: 'Select the target start date',
    );

    if (picked != null) {
      controller.setStartDate(picked);
    }
  }

  Future<void> _save(BuildContext context, {required bool submitAfterSave}) async {
    FocusScope.of(context).unfocus();

    final ApplicationFormController controller =
        context.read<ApplicationFormController>();

    final bool saved = await controller.save(submitAfterSave: submitAfterSave);

    if (!context.mounted) {
      return;
    }

    if (saved) {
      final String message =
          controller.successMessage ?? 'Application saved successfully.';

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );

      Navigator.of(context).maybePop();
      return;
    }

    if (controller.errorMessage != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(controller.errorMessage!),
          backgroundColor: AppColors.danger,
        ),
      );
    }
  }

  Widget _buildFieldError(String? message) {
    if (message == null) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.only(top: 5, left: 4),
      child: Text(
        message,
        style: const TextStyle(color: AppColors.danger, fontSize: 12),
      ),
    );
  }

  Widget _buildForm(BuildContext context, ApplicationFormController controller) {
    final ThemeData theme = Theme.of(context);
    final bool isEditing = existing != null;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text(
              isEditing ? 'Edit application' : 'New internship application',
              style: theme.textTheme.titleLarge,
            ),
            const SizedBox(height: 4),
            Text(
              isEditing
                  ? 'Update your details, then submit for adviser review.'
                  : 'Provide the host company, target role, required hours, and '
                      'target start date. You can save as a draft and submit later.',
              style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
            ),
            const SizedBox(height: 20),
            TextFormField(
              controller: controller.hostCompanyController,
              textInputAction: TextInputAction.next,
              inputFormatters: <TextInputFormatter>[
                LengthLimitingTextInputFormatter(255),
              ],
              decoration: InputDecoration(
                labelText: 'Host company *',
                prefixIcon: const Icon(Icons.business_rounded),
                helperText: 'Fictional test company or partner organization.',
                errorText: controller.fieldError('host_company_name'),
              ),
              validator: (String? value) =>
                  (value == null || value.trim().isEmpty)
                      ? 'Host company is required.'
                      : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: controller.targetRoleController,
              textInputAction: TextInputAction.next,
              inputFormatters: <TextInputFormatter>[
                LengthLimitingTextInputFormatter(255),
              ],
              decoration: InputDecoration(
                labelText: 'Target role *',
                prefixIcon: const Icon(Icons.badge_rounded),
                errorText: controller.fieldError('target_role'),
              ),
              validator: (String? value) =>
                  (value == null || value.trim().isEmpty)
                      ? 'Target role is required.'
                      : null,
            ),
            const SizedBox(height: 14),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Expanded(
                  child: TextFormField(
                    controller: controller.hoursController,
                    keyboardType: TextInputType.number,
                    inputFormatters: <TextInputFormatter>[
                      FilteringTextInputFormatter.digitsOnly,
                      LengthLimitingTextInputFormatter(4),
                    ],
                    decoration: InputDecoration(
                      labelText: 'Required internship hours *',
                      prefixIcon: const Icon(Icons.schedule_rounded),
                      helperText: 'Whole number between 1 and 5000.',
                      errorText:
                          controller.fieldError('internship_hours_required'),
                    ),
                    validator: (String? value) {
                      final int? hours = int.tryParse(value?.trim() ?? '');
                      if (value == null || value.trim().isEmpty || hours == null) {
                        return 'Enter the required hours.';
                      }
                      if (hours < 1) {
                        return 'Must be at least 1.';
                      }
                      if (hours > 5000) {
                        return 'Must not exceed 5000.';
                      }
                      return null;
                    },
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: InkWell(
                    onTap: () => _pickStartDate(context, controller),
                    borderRadius: BorderRadius.circular(12),
                    child: InputDecorator(
                      decoration: const InputDecoration(
                        labelText: 'Target start date *',
                        prefixIcon: Icon(Icons.event_rounded),
                      ),
                      child: Text(
                        controller.startDateText.isEmpty
                            ? 'Select date'
                            : DateFormatX.date(
                                DateFormatX.dateInputValue(
                                  controller.startDate?.toIso8601String(),
                                ),
                              ),
                        style: theme.textTheme.bodyLarge?.copyWith(
                          color: controller.startDate == null
                              ? AppColors.textSecondary
                              : AppColors.textPrimary,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
            _buildFieldError(controller.fieldError('target_start_date')),
            const SizedBox(height: 18),
            Divider(color: AppColors.border),
            const SizedBox(height: 14),
            Row(
              children: <Widget>[
                const Icon(
                  Icons.fact_check_outlined,
                  size: 18,
                  color: AppColors.primary,
                ),
                const SizedBox(width: 8),
                Text('Requirements checklist', style: theme.textTheme.titleMedium),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              'All four requirements must be prepared before an adviser can verify them.',
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: 6),
            for (final MapEntry<String, String> entry
                in _checklistLabels.entries)
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: controller.checklist[entry.key] ?? false,
                onChanged: (bool value) =>
                    controller.toggleChecklist(entry.key, value),
                title: Text(entry.value),
                secondary: Icon(
                  (controller.checklist[entry.key] ?? false)
                      ? Icons.check_circle_rounded
                      : Icons.radio_button_unchecked_rounded,
                  color: (controller.checklist[entry.key] ?? false)
                      ? AppColors.secondary
                      : AppColors.textSecondary,
                ),
              ),
            if (isEditing && existing?.status == ApplicationStatus.revisionRequested) ...<Widget>[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.warning.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: AppColors.warning.withValues(alpha: 0.4),
                  ),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    const Icon(Icons.info_outline_rounded, color: AppColors.warning, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'A revision was requested. Update the details below and '
                        'resubmit to continue the workflow.',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 12),
            TextFormField(
              controller: controller.remarksController,
              maxLines: 2,
              maxLength: 500,
              decoration: const InputDecoration(
                labelText: 'Remarks for the reviewer (optional)',
                alignLabelWithHint: true,
                helperText: 'Shown to the adviser or coordinator on submission.',
              ),
            ),
            const SizedBox(height: 18),
            if (controller.errorMessage != null)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.danger.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: AppColors.danger.withValues(alpha: 0.4),
                  ),
                ),
                child: Row(
                  children: <Widget>[
                    const Icon(Icons.error_outline_rounded, color: AppColors.danger, size: 18),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        controller.errorMessage!,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            if (controller.errorMessage != null) const SizedBox(height: 14),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: <Widget>[
                OutlinedButton.icon(
                  onPressed: controller.saving
                      ? null
                      : () => Navigator.of(context).maybePop(),
                  icon: const Icon(Icons.close_rounded, size: 18),
                  label: const Text('Cancel'),
                ),
                FilledButton.tonalIcon(
                  onPressed: controller.saving
                      ? null
                      : () => _save(context, submitAfterSave: false),
                  icon: controller.saving
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.save_outlined, size: 18),
                  label: Text(isEditing ? 'Save changes' : 'Save draft'),
                ),
                FilledButton.icon(
                  onPressed: controller.saving
                      ? null
                      : () => _save(context, submitAfterSave: true),
                  icon: const Icon(Icons.send_rounded, size: 18),
                  label: const Text('Submit for review'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ApplicationFormController controller =
        context.watch<ApplicationFormController>();

    final Widget body = SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 820),
            child: _buildForm(context, controller),
          ),
        ),
      ),
    );

    if (embedded) {
      return body;
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(
          existing == null ? 'New internship application' : 'Edit application',
        ),
      ),
      body: body,
    );
  }
}