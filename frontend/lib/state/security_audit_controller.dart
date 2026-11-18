import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../models/security_audit_event_model.dart';
import '../models/security_posture_model.dart';
import '../services/security_service.dart';

class SecurityAuditController extends ChangeNotifier {
  SecurityAuditController({
    required this.service,
    this.canManage = false,
  }) {
    load();
  }

  final SecurityService service;

  final bool canManage;

  bool _loading = false;

  ApiException? _loadError;

  SecurityPostureModel? _posture;

  List<SecurityAuditEventModel> _events = const <SecurityAuditEventModel>[];

  SecurityEventType? _eventFilter;

  SecuritySeverity? _severityFilter;

  bool get loading => _loading;

  ApiException? get loadError => _loadError;

  SecurityPostureModel? get posture => _posture;

  List<SecurityAuditEventModel> get events => _events;

  SecurityEventType? get eventFilter => _eventFilter;

  SecuritySeverity? get severityFilter => _severityFilter;

  int get filteredCount => _events.length;

  List<SecurityAuditEventModel> get highSeverityEvents =>
      _events.where((event) => event.severity == SecuritySeverity.high).toList(growable: false);

  List<SecurityAuditEventModel> get loginFailedEvents =>
      _events.where((event) => event.eventType == SecurityEventType.loginFailed).toList(growable: false);

  Future<void> load() async {
    if (!canManage || _loading) {
      return;
    }

    _loading = true;
    _loadError = null;
    notifyListeners();

    try {
      final List<SecurityAuditEventModel> events = await service.auditEvents(
        eventType: _eventFilter,
        severity: _severityFilter,
      );

      SecurityPostureModel? posture;

      try {
        posture = await service.posture();
      } on ApiException {
        posture = null;
      }

      _events = events;
      _posture = posture;
    } on ApiException catch (error) {
      _loadError = error;
    } catch (error) {
      _loadError = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while loading security telemetry: $error',
      );
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> setEventFilter(SecurityEventType? type) async {
    _eventFilter = type;

    await load();
  }

  Future<void> setSeverityFilter(SecuritySeverity? severity) async {
    _severityFilter = severity;

    await load();
  }

  void clearError() {
    if (_loadError == null) {
      return;
    }

    _loadError = null;
    notifyListeners();
  }
}