import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../models/automation_job_model.dart';
import '../models/automation_sweep_result.dart';
import '../services/automation_service.dart';

class AutomationMonitorController extends ChangeNotifier {
  AutomationMonitorController({
    required this.service,
    required this.canManage,
  });

  final AutomationService service;

  final bool canManage;

  bool _loading = true;

  bool _running = false;

  bool _requeuing = false;

  ApiException? _loadError;

  ApiException? _actionError;

  String? _successMessage;

  AutomationJobStatus? _statusFilter;

  List<AutomationJobModel> _jobs = const <AutomationJobModel>[];

  AutomationWeeklySummary? _weeklySummary;

  bool get loading => _loading;

  bool get running => _running;

  bool get requeuing => _requeuing;

  ApiException? get loadError => _loadError;

  ApiException? get actionError => _actionError;

  String? get successMessage => _successMessage;

  AutomationJobStatus? get statusFilter => _statusFilter;

  List<AutomationJobModel> get jobs => _jobs;

  AutomationWeeklySummary? get weeklySummary => _weeklySummary;

  List<AutomationJobModel> get manualReviewJobs => _jobs
      .where((AutomationJobModel job) => job.status.needsCoordinatorRecovery)
      .toList(growable: false);

  Future<void> load() async {
    _loading = true;
    _loadError = null;
    notifyListeners();

    try {
      _jobs = await service.jobs(status: _statusFilter);
      _weeklySummary = await _loadWeeklySummary();
    } on ApiException catch (error) {
      _loadError = error;
    } catch (error) {
      _loadError = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while loading automation jobs: $error',
      );
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> setStatusFilter(AutomationJobStatus? status) async {
    if (_statusFilter == status) {
      return;
    }

    _statusFilter = status;
    notifyListeners();

    await load();
  }

  Future<AutomationSweepResult?> runSweep({
    required AutomationSweepType type,
    AutomationFaultMode faultMode = AutomationFaultMode.none,
  }) async {
    if (_running) {
      return null;
    }

    _running = true;
    _actionError = null;
    _successMessage = null;
    notifyListeners();

    try {
      final AutomationSweepResult result = await service.runSweep(
        type: type,
        faultMode: faultMode,
      );

      _successMessage = '${type.label} sweep finished — '
          '${result.enqueued} enqueued, ${result.dispatch.summary}.';

      await load();

      return result;
    } on ApiException catch (error) {
      _actionError = error;
      return null;
    } catch (error) {
      _actionError = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while running the sweep: $error',
      );
      return null;
    } finally {
      _running = false;
      notifyListeners();
    }
  }

  Future<bool> requeue(
    AutomationJobModel job, {
    String? recipientIdentifier,
    String? remarks,
  }) async {
    if (_requeuing) {
      return false;
    }

    _requeuing = true;
    _actionError = null;
    _successMessage = null;
    notifyListeners();

    try {
      final AutomationJobModel updated = await service.requeue(
        job.id,
        recipientIdentifier: recipientIdentifier,
        remarks: remarks,
      );

      _successMessage = 'Job #${updated.id} requeued — ${updated.status.label}.';

      await load();

      return true;
    } on ApiException catch (error) {
      _actionError = error;
      return false;
    } catch (error) {
      _actionError = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while requeueing the job: $error',
      );
      return false;
    } finally {
      _requeuing = false;
      notifyListeners();
    }
  }

  Future<AutomationWeeklySummary?> _loadWeeklySummary() async {
    try {
      return await service.weeklySummary();
    } on ApiException {
      return null;
    }
  }

  void clearActionError() {
    if (_actionError == null) {
      return;
    }

    _actionError = null;
    notifyListeners();
  }

  void clearSuccess() {
    if (_successMessage == null) {
      return;
    }

    _successMessage = null;
    notifyListeners();
  }
}