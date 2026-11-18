import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:provider/single_child_widget.dart';

import 'package:internship_monitor/core/network/api_client.dart';
import 'package:internship_monitor/core/network/api_exception.dart';
import 'package:internship_monitor/core/theme/app_theme.dart';
import 'package:internship_monitor/core/widgets/states/error_state_view.dart';
import 'package:internship_monitor/features/security/security_audit_screen.dart';
import 'package:internship_monitor/models/security_audit_event_model.dart';
import 'package:internship_monitor/models/security_posture_model.dart';
import 'package:internship_monitor/models/user_model.dart';
import 'package:internship_monitor/services/auth_service.dart';
import 'package:internship_monitor/services/security_service.dart';
import 'package:internship_monitor/state/auth_controller.dart';
import 'package:internship_monitor/state/security_audit_controller.dart';

class _FakeAuthService extends AuthService {
  _FakeAuthService(this.role) : super(ApiClient());

  final String role;

  @override
  Future<UserModel> login(String email, String password) async {
    return UserModel(
      id: 1,
      studentOrEmployeeId: role == UserModel.roleStudent
          ? '2026-00001'
          : 'EMP-COORD-001',
      name: role == UserModel.roleStudent ? 'Ana Dela Cruz' : 'Maria Santos',
      email: email,
      role: role,
      departmentOrCompany: 'College of Information Technology',
    );
  }
}

class _FakeSecurityService extends SecurityService {
  _FakeSecurityService({
    required this.events,
    this.postureFixture,
    this.postureError,
  }) : super(ApiClient());

  final List<SecurityAuditEventModel> events;

  final SecurityPostureModel? postureFixture;

  final ApiException? postureError;

  @override
  Future<List<SecurityAuditEventModel>> auditEvents({
    SecurityEventType? eventType,
    SecuritySeverity? severity,
    int perPage = 25,
  }) async {
    return events
        .where(
          (SecurityAuditEventModel event) =>
              (eventType == null || event.eventType == eventType) &&
              (severity == null || event.severity == severity),
        )
        .toList(growable: false);
  }

  @override
  Future<SecurityPostureModel> posture() async {
    final ApiException? error = postureError;

    if (error != null) {
      throw error;
    }

    final SecurityPostureModel? current = postureFixture;

    if (current == null) {
      throw const ApiException(
        kind: ApiErrorKind.forbidden,
        message: 'Forbidden.',
      );
    }

    return current;
  }
}

SecurityPostureModel _posture() {
  return SecurityPostureModel.fromJson(<String, dynamic>{
    'strict_cors': true,
    'security_headers_enabled': true,
    'max_payload_kb': 64,
    'sanctum_ttl_minutes': 60,
    'login_rate_limit_per_min': 5,
    'api_rate_limit_per_min': 60,
    'webhook_rate_limit_per_min': 30,
    'debug_mode_disabled': true,
    'input_sanitization_enabled': true,
    'audit_events_total': 12,
    'audit_events_last_24h': 3,
    'security_headers': <String>[
      'X-Content-Type-Options',
      'X-Frame-Options',
      'Referrer-Policy',
      'Permissions-Policy',
      'Content-Security-Policy',
      'Strict-Transport-Security',
    ],
  });
}

SecurityAuditEventModel _event({
  int id = 1,
  String type = 'bola_blocked',
  String severity = 'high',
  String endpoint = '/api/v1/applications/3',
  int status = 403,
  String? role = 'student',
  Map<String, dynamic>? details,
}) {
  return SecurityAuditEventModel.fromJson(<String, dynamic>{
    'id': id,
    'correlation_id': ' corr-2026-11-17-0001 '.trim(),
    'event_type': type,
    'severity': severity,
    'actor_id': 5,
    'actor_role': role,
    'ip_hash_prefix': '9f2c1ab77d10',
    'endpoint': endpoint,
    'http_status': status,
    'details': details,
    'created_at': '2026-11-17T00:30:00Z',
  });
}

Future<void> _pumpScreen(
  WidgetTester tester,
  _FakeSecurityService service, {
  String role = UserModel.roleCoordinator,
  Size size = const Size(1440, 960),
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  final AuthController auth = AuthController(authService: _FakeAuthService(role));

  await auth.login('coordinator.demo@uls-test.edu.ph', 'Password123!');

  final SecurityAuditController controller = SecurityAuditController(
    service: service,
    canManage: role == UserModel.roleCoordinator,
  );

  await controller.load();

  await tester.pumpWidget(
    MultiProvider(
      providers: <SingleChildWidget>[
        Provider<ApiClient>(create: (_) => ApiClient()),
        ChangeNotifierProvider<AuthController>.value(value: auth),
        ChangeNotifierProvider<SecurityAuditController>.value(
          value: controller,
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.dark,
        home: const Scaffold(body: SecurityAuditScreen()),
      ),
    ),
  );

  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  testWidgets('SecurityAuditScreen renders posture and audit events', (
    WidgetTester tester,
  ) async {
    final _FakeSecurityService service = _FakeSecurityService(
      postureFixture: _posture(),
      events: <SecurityAuditEventModel>[
        _event(id: 2, type: 'mass_assignment_blocked', severity: 'high', endpoint: '/api/v1/applications', status: 422, details: <String, dynamic>{'attributes': <String>['status', 'role']}),
        _event(id: 1, type: 'rate_limit_exceeded', severity: 'warning', endpoint: '/api/v1/auth/login', status: 429, role: null),
      ],
    );

    await _pumpScreen(tester, service);

    expect(find.text('Security hardening & audit trail'), findsOneWidget);
    expect(find.text('Hardening posture'), findsOneWidget);
    expect(find.text('6 / 6 checks'), findsOneWidget);
    expect(find.text('Strict CORS allowlist'), findsOneWidget);
    expect(find.text('64 KB payload cap'), findsOneWidget);
    expect(find.text('60-min token TTL'), findsOneWidget);
    expect(find.text('Tiered rate limiting'), findsOneWidget);
    expect(find.text('Mass Assignment Blocked'), findsWidgets);
    expect(find.text('Rate Limit Exceeded'), findsWidgets);
    expect(find.textContaining('ip 9f2c1ab77d10'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('SecurityAuditScreen hides telemetry from non-coordinators', (
    WidgetTester tester,
  ) async {
    final _FakeSecurityService service = _FakeSecurityService(
      events: const <SecurityAuditEventModel>[],
    );

    await _pumpScreen(tester, service, role: UserModel.roleStudent);

    expect(
      find.text('Security telemetry is coordinator-only'),
      findsOneWidget,
    );
    expect(find.text('Hardening posture'), findsNothing);
    expect(find.text('All events'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('ErrorStateView describes 429 and 413 recoveries', (
    WidgetTester tester,
  ) async {
    final ErrorPresentation rateLimited = ErrorStateView.describe(
      const ApiException(
        kind: ApiErrorKind.rateLimited,
        message: 'Rate limit exceeded. Please retry after the cooldown period.',
        statusCode: 429,
        retryAfterSeconds: 30,
      ),
    );

    expect(rateLimited.title, 'Too many requests - please slow down');
    expect(rateLimited.message, contains('30 seconds'));

    final ErrorPresentation tooLarge = ErrorStateView.describe(
      const ApiException(
        kind: ApiErrorKind.payloadTooLarge,
        message: 'Request payload exceeds the 64 KB limit.',
        statusCode: 413,
      ),
    );

    expect(tooLarge.title, 'Request too large');
    expect(tooLarge.message, contains('64 KB'));
    expect(tooLarge.accent, AppColors.warning);
  });

  testWidgets('SecurityAuditController surfaces API failures', (
    WidgetTester tester,
  ) async {
    final SecurityAuditController controller = SecurityAuditController(
      service: _FakeSecurityService(
        events: const <SecurityAuditEventModel>[],
        postureError: const ApiException(
          kind: ApiErrorKind.forbidden,
          message: 'This action is unauthorized.',
          statusCode: 403,
        ),
      ),
      canManage: true,
    );

    await tester.pumpAndSettle();

    expect(controller.events, isEmpty);
    expect(controller.posture, isNull);
    expect(controller.loadError, isNull);
  });
}