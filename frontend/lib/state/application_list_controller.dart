import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../models/application_model.dart';
import '../models/application_status.dart';
import '../services/application_service.dart';

class ApplicationListController extends ChangeNotifier {
  ApplicationListController({required this.service});

  final ApplicationService service;

  bool _loading = false;

  List<ApplicationModel> _applications = <ApplicationModel>[];

  ApiException? _error;

  ApplicationStatus? _statusFilter;

  bool get loading => _loading;

  List<ApplicationModel> get applications {
    final ApplicationStatus? filter = _statusFilter;

    if (filter == null) {
      return _applications;
    }

    return _applications
        .where((application) => application.status == filter)
        .toList(growable: false);
  }

  List<ApplicationModel> get allApplications =>
      List<ApplicationModel>.unmodifiable(_applications);

  ApiException? get error => _error;

  ApplicationStatus? get statusFilter => _statusFilter;

  bool get isEmpty => !_loading && _error == null && _applications.isEmpty;

  bool get isFilteredEmpty =>
      !_loading && _error == null && _applications.isNotEmpty && applications.isEmpty;

  int countFor(ApplicationStatus status) {
    return _applications
        .where((application) => application.status == status)
        .length;
  }

  Map<ApplicationStatus, int> get statusCounts {
    final Map<ApplicationStatus, int> counts = <ApplicationStatus, int>{};

    for (final ApplicationModel application in _applications) {
      counts[application.status] = (counts[application.status] ?? 0) + 1;
    }

    return counts;
  }

  Future<void> load() async {
    if (_loading) {
      return;
    }

    _loading = true;
    _error = null;
    notifyListeners();

    try {
      final List<ApplicationModel> applications = await service.list();
      _applications = applications;
    } on ApiException catch (error) {
      _error = error;
    } catch (error) {
      _error = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while loading applications: $error',
      );
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  void setStatusFilter(ApplicationStatus? status) {
    if (_statusFilter == status) {
      return;
    }

    _statusFilter = status;
    notifyListeners();
  }

  void clearError() {
    if (_error == null) {
      return;
    }

    _error = null;
    notifyListeners();
  }
}