import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../models/user_model.dart';
import '../services/auth_service.dart';

class AuthController extends ChangeNotifier {
  AuthController({required this.authService});

  final AuthService authService;

  bool _busy = false;

  UserModel? _user;

  ApiException? _error;

  bool _sessionExpired = false;

  bool get busy => _busy;

  UserModel? get user => _user;

  bool get isAuthenticated => _user != null;

  ApiException? get error => _error;

  bool get sessionExpired => _sessionExpired;

  Future<void> login(String email, String password) async {
    if (_busy) {
      return;
    }

    _busy = true;
    _error = null;
    _sessionExpired = false;
    notifyListeners();

    try {
      final UserModel user = await authService.login(email, password);
      _user = user;
    } on ApiException catch (error) {
      _error = error;
    } catch (error) {
      _error = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while signing in: $error',
      );
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    if (_busy) {
      return;
    }

    _busy = true;
    notifyListeners();

    try {
      await authService.logout();
    } finally {
      _user = null;
      _error = null;
      _sessionExpired = false;
      _busy = false;
      notifyListeners();
    }
  }

  void expireSession() {
    if (_user == null) {
      return;
    }

    _user = null;
    _error = null;
    _sessionExpired = true;
    notifyListeners();
  }

  void clearError() {
    if (_error == null) {
      return;
    }

    _error = null;
    notifyListeners();
  }

  void dismissSessionNotice() {
    if (!_sessionExpired) {
      return;
    }

    _sessionExpired = false;
    notifyListeners();
  }
}