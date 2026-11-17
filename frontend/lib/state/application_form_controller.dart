import 'package:flutter/widgets.dart';

import '../core/network/api_exception.dart';
import '../core/utils/date_format.dart';
import '../models/application_model.dart';
import '../models/application_status.dart';
import '../services/application_service.dart';

class ApplicationFormController extends ChangeNotifier {
  ApplicationFormController({
    required this.service,
    this.existing,
  });

  final ApplicationService service;

  final ApplicationModel? existing;

  final TextEditingController hostCompanyController = TextEditingController();

  final TextEditingController targetRoleController = TextEditingController();

  final TextEditingController hoursController = TextEditingController();

  final TextEditingController remarksController = TextEditingController();

  DateTime? startDate;

  final Map<String, bool> checklist = <String, bool>{
    for (final String key in ApplicationModel.checklistKeys) key: false,
  };

  bool _initialized = false;

  bool _saving = false;

  Map<String, String?> _fieldErrors = <String, String?>{};

  String? _errorMessage;

  String? _successMessage;

  ApplicationModel? _savedApplication;

  bool get saving => _saving;

  Map<String, String?> get fieldErrors =>
      Map<String, String?>.unmodifiable(_fieldErrors);

  String? get errorMessage => _errorMessage;

  String? get successMessage => _successMessage;

  bool get isEditing => existing != null;

  String? fieldError(String key) => _fieldErrors[key];

  String get startDateText =>
      startDate == null ? '' : DateFormatX.dateInputValue(startDate?.toIso8601String());

  bool get isValidStart => startDate != null;

  void initialise() {
    if (_initialized) {
      return;
    }

    _initialized = true;

    final ApplicationModel? model = existing;

    if (model == null) {
      checklist.addAll(<String, bool>{
        for (final String key in ApplicationModel.checklistKeys) key: false,
      });
      startDate = DateTime.now().add(const Duration(days: 30));
      notifyListeners();
      return;
    }

    hostCompanyController.text = model.hostCompanyName;
    targetRoleController.text = model.targetRole;
    hoursController.text = model.internshipHoursRequired.toString();
    startDate = DateTime.tryParse(model.targetStartDate);
    checklist.addAll(model.requirementsChecklist);

    notifyListeners();
  }

  void setStartDate(DateTime? value) {
    startDate = value;
    _fieldErrors = _withoutKey(_fieldErrors, 'target_start_date');
    notifyListeners();
  }

  void toggleChecklist(String key, bool value) {
    checklist[key] = value;
    notifyListeners();
  }

  Future<bool> save({bool submitAfterSave = false}) async {
    if (_saving) {
      return false;
    }

    final Map<String, String?> clientErrors = _validateClientSide();

    if (clientErrors.isNotEmpty) {
      _fieldErrors = clientErrors;
      _errorMessage = 'Please correct the highlighted fields before submitting.';
      notifyListeners();
      return false;
    }

    _saving = true;
    _errorMessage = null;
    _successMessage = null;
    _fieldErrors = <String, String?>{};
    notifyListeners();

    try {
      _savedApplication ??= await _persist();

      if (submitAfterSave) {
        _savedApplication = await service.transition(
          _savedApplication!.id,
          <String, dynamic>{
            'target_status': ApplicationStatus.submitted.wire,
            'remarks': remarksController.text.trim().isEmpty
                ? 'Submitted from the internship application monitor.'
                : remarksController.text.trim(),
          },
        );
      }

      _successMessage = submitAfterSave
          ? 'Application submitted successfully.'
          : 'Application saved successfully.';

      return true;
    } on ApiException catch (error) {
      _errorMessage = error.message;
      _fieldErrors = _withoutNullValues(error.firstErrorMessages());
      return false;
    } catch (error) {
      _errorMessage = 'Unexpected error while saving the application: $error';
      return false;
    } finally {
      _saving = false;
      notifyListeners();
    }
  }

  Future<ApplicationModel> _persist() {
    final Map<String, dynamic> payload = <String, dynamic>{
      'host_company_name': hostCompanyController.text.trim(),
      'target_role': targetRoleController.text.trim(),
      'internship_hours_required': int.tryParse(hoursController.text.trim()) ?? 0,
      'target_start_date': startDate == null
          ? ''
          : DateFormatX.dateInputValue(startDate?.toIso8601String()),
      'requirements_checklist': Map<String, bool>.from(checklist),
    };

    final ApplicationModel? model = existing;

    if (model == null) {
      return service.create(payload);
    }

    return service.update(model.id, payload);
  }

  Map<String, String?> _validateClientSide() {
    final Map<String, String?> errors = <String, String?>{};

    final String hostCompany = hostCompanyController.text.trim();
    if (hostCompany.isEmpty) {
      errors['host_company_name'] = 'Host company is required.';
    }

    final String targetRole = targetRoleController.text.trim();
    if (targetRole.isEmpty) {
      errors['target_role'] = 'Target role is required.';
    }

    final String hoursText = hoursController.text.trim();
    final int? hours = int.tryParse(hoursText);
    if (hoursText.isEmpty || hours == null) {
      errors['internship_hours_required'] = 'Required internship hours must be a number.';
    } else if (hours < 1) {
      errors['internship_hours_required'] = 'Required internship hours must be at least 1.';
    } else if (hours > 5000) {
      errors['internship_hours_required'] = 'Required internship hours must not exceed 5000.';
    }

    if (startDate == null) {
      errors['target_start_date'] = 'Target start date is required.';
    }

    return errors;
  }

  Map<String, String?> _withoutKey(Map<String, String?> source, String key) {
    final Map<String, String?> copy = <String, String?>{...source}..remove(key);
    return copy;
  }

  Map<String, String?> _withoutNullValues(Map<String, String?> source) {
    final Map<String, String?> copy = <String, String?>{...source}
      ..removeWhere((_, value) => value == null);
    return copy;
  }

  @override
  void dispose() {
    hostCompanyController.dispose();
    targetRoleController.dispose();
    hoursController.dispose();
    remarksController.dispose();
    super.dispose();
  }
}