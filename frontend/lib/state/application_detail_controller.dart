import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../models/application_action.dart';
import '../models/application_model.dart';
import '../services/application_service.dart';

class ApplicationDetailController extends ChangeNotifier {
  ApplicationDetailController({
    required this.service,
    required this.applicationId,
  });

  final ApplicationService service;

  final int applicationId;

  bool _loading = true;

  bool _acting = false;

  ApplicationModel? _application;

  ApiException? _loadError;

  ApiException? _actionError;

  String? _successMessage;

  bool get loading => _loading;

  bool get acting => _acting;

  ApplicationModel? get application => _application;

  ApiException? get loadError => _loadError;

  ApiException? get actionError => _actionError;

  String? get successMessage => _successMessage;

  Future<void> load() async {
    _loading = true;
    _loadError = null;
    _actionError = null;
    notifyListeners();

    try {
      _application = await service.show(applicationId);
    } on ApiException catch (error) {
      _loadError = error;
    } catch (error) {
      _loadError = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while loading the application: $error',
      );
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<bool> performAction(
    ApplicationAction action, {
    String? remarks,
    int? partnerReviewerId,
    String? interviewSchedule,
    String? interviewNotes,
  }) async {
    if (_acting) {
      return false;
    }

    _acting = true;
    _actionError = null;
    _successMessage = null;
    notifyListeners();

    final Map<String, dynamic> payload = <String, dynamic>{
      'target_status': action.status.wire,
    };

    if (remarks != null && remarks.trim().isNotEmpty) {
      payload['remarks'] = remarks.trim();
    }

    if (partnerReviewerId != null) {
      payload['partner_reviewer_id'] = partnerReviewerId;
    }

    if (interviewSchedule != null && interviewSchedule.trim().isNotEmpty) {
      payload['interview_schedule'] = interviewSchedule.trim();
    }

    if (interviewNotes != null && interviewNotes.trim().isNotEmpty) {
      payload['interview_notes'] = interviewNotes.trim();
    }

    try {
      _application = await service.transition(applicationId, payload);
      _successMessage = '${action.label} completed.';
      return true;
    } on ApiException catch (error) {
      _actionError = error;
      return false;
    } catch (error) {
      _actionError = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while updating the application: $error',
      );
      return false;
    } finally {
      _acting = false;
      notifyListeners();
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