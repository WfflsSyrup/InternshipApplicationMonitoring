import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:provider/single_child_widget.dart';

import 'package:internship_monitor/core/network/api_client.dart';
import 'package:internship_monitor/core/theme/app_theme.dart';
import 'package:internship_monitor/features/crypto/crypto_integration_screen.dart';
import 'package:internship_monitor/models/application_model.dart';
import 'package:internship_monitor/models/application_status.dart';
import 'package:internship_monitor/models/crypto_envelope_model.dart';
import 'package:internship_monitor/models/crypto_keyring_model.dart';
import 'package:internship_monitor/models/crypto_simulation_result.dart';
import 'package:internship_monitor/models/user_model.dart';
import 'package:internship_monitor/services/application_service.dart';
import 'package:internship_monitor/services/auth_service.dart';
import 'package:internship_monitor/services/crypto_service.dart';
import 'package:internship_monitor/state/auth_controller.dart';
import 'package:internship_monitor/state/crypto_integration_controller.dart';

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
      name: role == UserModel.roleStudent ? 'Juana Dela Cruz' : 'Maria Santos',
      email: email,
      role: role,
      departmentOrCompany: 'Internship Office',
    );
  }
}

class _FakeCryptoService extends CryptoService {
  _FakeCryptoService({
    required this.records,
    this.keyring,
    this.simulation,
  }) : super(ApiClient());

  final List<CryptoEnvelopeModel> records;

  final CryptoKeyringStatus? keyring;

  final CryptoSimulationResult? simulation;

  int simulationRuns = 0;

  @override
  Future<List<CryptoEnvelopeModel>> envelopes({
    CryptoVerificationStatus? status,
    CryptoDirection? direction,
    int? applicationId,
  }) async {
    return records
        .where(
          (CryptoEnvelopeModel envelope) =>
              (status == null || envelope.status == status) &&
              (direction == null ||
                  !direction.isFilterable ||
                  envelope.direction == direction),
        )
        .toList(growable: false);
  }

  @override
  Future<CryptoKeyringStatus> keyringStatus() async {
    final CryptoKeyringStatus? current = keyring;

    if (current == null) {
      throw Exception('keyring not available for this role');
    }

    return current;
  }

  @override
  Future<CryptoEnvelopeModel> dispatchPartnerPacket({
    required int applicationId,
    String? remarks,
  }) async {
    return records.first;
  }

  @override
  Future<CryptoSimulationResult> runSimulation({
    required CryptoSimulationMode mode,
    String? referenceCode,
    String? outcome,
  }) async {
    simulationRuns += 1;

    final CryptoSimulationResult? current = simulation;

    if (current == null) {
      throw Exception('no simulation fixture');
    }

    return current;
  }
}

class _FakeApplicationService extends ApplicationService {
  _FakeApplicationService() : super(ApiClient());

  @override
  Future<List<ApplicationModel>> list({ApplicationStatus? status}) async {
    return const <ApplicationModel>[];
  }
}

CryptoEnvelopeModel _envelope({
  int id = 1,
  String correlationId = 'corr-2026-11-17-0001',
  String direction = 'outbound_partner_packet',
  String keyId = 'v2',
  String status = 'verified',
  String? applicationRef = 'INT-2026-0003',
  int? applicationId = 3,
  String? failureReason,
}) {
  return CryptoEnvelopeModel.fromJson(<String, dynamic>{
    'id': id,
    'correlation_id': correlationId,
    'application_id': applicationId,
    'application_ref': applicationRef,
    'direction': direction,
    'key_id': keyId,
    'cipher_suite': 'AES-256-GCM+HMAC-SHA256',
    'signature_verified': status == 'verified',
    'replay_checked': false,
    'verification_status': status,
    'failure_reason': failureReason,
    'payload_sha256':
        '3f786850e387550fdab836ed7e6dc881de23001b927ec7c7a75c3f7a1d0e2f1c',
    'created_at': '2026-11-17T00:30:00Z',
  });
}

CryptoKeyringStatus _keyring() {
  return CryptoKeyringStatus.fromJson(<String, dynamic>{
    'active_kid': 'v2',
    'supported_kids': <String>['v1', 'v2'],
    'cipher_suite': 'AES-256-GCM+HMAC-SHA256',
    'signature_algorithm': 'HMAC-SHA256',
    'iv_length_bytes': 12,
    'tag_length_bytes': 16,
    'clock_skew_seconds': 300,
    'tamper_simulation_enabled': true,
    'key_material_exposed': false,
    'client_keys_embedded': 0,
    'totals': <String, dynamic>{
      'envelopes': 4,
      'verified': 3,
      'rejected': 1,
    },
  });
}

CryptoSimulationResult _simulation() {
  return CryptoSimulationResult.fromJson(<String, dynamic>{
    'mode': 'valid_callback',
    'expected_outcome': 'accepted',
    'accepted': true,
    'matched_expectation': true,
    'http_status': 200,
    'reason_code': null,
    'message': 'Inbound callback verified and applied to the application.',
    'reference_code': 'INT-2026-0003',
    'outcome': 'interview_passed',
    'key_id': 'v2',
    'cipher_suite': 'AES-256-GCM+HMAC-SHA256',
    'correlation_id': 'SIM-2026-0003-01',
    'envelope_id': 12,
    'duration_ms': 6,
    'steps': <Map<String, dynamic>>[
      <String, dynamic>{
        'name': 'signature',
        'result': 'verified',
        'detail': 'HMAC-SHA256 matched key v2',
      },
      <String, dynamic>{
        'name': 'decrypt',
        'result': 'verified',
        'detail': 'AES-256-GCM tag validated, AAD matched',
      },
    ],
  });
}

Future<void> _pumpScreen(
  WidgetTester tester,
  _FakeCryptoService service, {
  String role = UserModel.roleCoordinator,
  Size size = const Size(1440, 960),
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  final AuthController auth = AuthController(
    authService: _FakeAuthService(role),
  );

  await auth.login('coordinator.demo@uls-test.edu.ph', 'Password123!');

  final CryptoIntegrationController controller = CryptoIntegrationController(
    service: service,
    canManage: role == UserModel.roleCoordinator,
  );

  await controller.load();

  await tester.pumpWidget(
    MultiProvider(
      providers: <SingleChildWidget>[
        Provider<ApiClient>(create: (_) => ApiClient()),
        Provider<ApplicationService>(create: (_) => _FakeApplicationService()),
        ChangeNotifierProvider<AuthController>.value(value: auth),
        ChangeNotifierProvider<CryptoIntegrationController>.value(
          value: controller,
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.dark,
        home: const Scaffold(body: CryptoIntegrationScreen()),
      ),
    ),
  );

  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  testWidgets('CryptoIntegrationScreen renders envelopes, keyring and counters', (
    WidgetTester tester,
  ) async {
    final _FakeCryptoService service = _FakeCryptoService(
      records: <CryptoEnvelopeModel>[
        _envelope(id: 7),
        _envelope(
          id: 6,
          correlationId: 'corr-2026-11-17-0002',
          direction: 'inbound_partner_callback',
          status: 'rejected_tamper',
          failureReason: 'AES-256-GCM authentication tag mismatch (422).',
        ),
      ],
      keyring: _keyring(),
    );

    await _pumpScreen(tester, service);

    expect(find.text('Secure integration & crypto audit'), findsOneWidget);
    expect(find.text('Keyring & policy'), findsOneWidget);
    expect(find.text('Active key v2'), findsOneWidget);
    expect(find.text('Total envelopes'), findsOneWidget);
    expect(find.text('Rejected'), findsOneWidget);
    expect(find.text('AES-256-GCM authentication tag mismatch (422).'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Seal & Dispatch'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Verification Lab'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('CryptoIntegrationScreen hides coordinator actions for students', (
    WidgetTester tester,
  ) async {
    final _FakeCryptoService service = _FakeCryptoService(
      records: <CryptoEnvelopeModel>[_envelope(id: 7)],
    );

    await _pumpScreen(tester, service, role: UserModel.roleStudent);

    expect(find.textContaining('Read-only view.'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Seal & Dispatch'), findsNothing);
    expect(find.widgetWithText(FilledButton, 'Verification Lab'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('CryptoIntegrationScreen opens the dispatch dialog and its guard', (
    WidgetTester tester,
  ) async {
    final _FakeCryptoService service = _FakeCryptoService(
      records: <CryptoEnvelopeModel>[_envelope(id: 7)],
      keyring: _keyring(),
    );

    await _pumpScreen(tester, service);

    await tester.tap(find.widgetWithText(FilledButton, 'Seal & Dispatch'));
    await tester.pumpAndSettle();

    expect(find.text('Seal & dispatch partner packet'), findsOneWidget);
    expect(
      find.textContaining(
        'No endorsed or partner-submitted application is available',
      ),
      findsOneWidget,
    );

    await tester.tap(find.widgetWithText(TextButton, 'Cancel'));
    await tester.pumpAndSettle();

    expect(find.text('Seal & dispatch partner packet'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('CryptoIntegrationScreen runs a controlled verification scenario', (
    WidgetTester tester,
  ) async {
    final _FakeCryptoService service = _FakeCryptoService(
      records: <CryptoEnvelopeModel>[_envelope(id: 7)],
      keyring: _keyring(),
      simulation: _simulation(),
    );

    await _pumpScreen(tester, service);

    await tester.tap(find.widgetWithText(FilledButton, 'Verification Lab'));
    await tester.pumpAndSettle();

    expect(find.text('Controlled crypto verification lab'), findsOneWidget);

    await tester.tap(find.widgetWithText(FilledButton, 'Run Verification'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(service.simulationRuns, 1);
    expect(find.text('Verification lab · Valid callback'), findsOneWidget);
    expect(find.text('Accepted · HTTP 200'), findsOneWidget);
    expect(find.text('Matched expectation'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}