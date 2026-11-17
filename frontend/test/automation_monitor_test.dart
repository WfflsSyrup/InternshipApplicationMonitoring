import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:provider/single_child_widget.dart';

import 'package:internship_monitor/core/network/api_client.dart';
import 'package:internship_monitor/core/theme/app_theme.dart';
import 'package:internship_monitor/features/automation/automation_monitor_screen.dart';
import 'package:internship_monitor/models/automation_job_model.dart';
import 'package:internship_monitor/models/automation_sweep_result.dart';
import 'package:internship_monitor/models/user_model.dart';
import 'package:internship_monitor/services/auth_service.dart';
import 'package:internship_monitor/services/automation_service.dart';
import 'package:internship_monitor/state/auth_controller.dart';
import 'package:internship_monitor/state/automation_monitor_controller.dart';

class _FakeAuthService extends AuthService {
  _FakeAuthService() : super(ApiClient());

  @override
  Future<UserModel> login(String email, String password) async {
    return UserModel(
      id: 1,
      studentOrEmployeeId: 'EMP-COORD-001',
      name: 'Maria Santos',
      email: email,
      role: UserModel.roleCoordinator,
      departmentOrCompany: 'Internship Office',
    );
  }
}

class _FakeAutomationService extends AutomationService {
  _FakeAutomationService(this.records, {this.weekly}) : super(ApiClient());

  final List<AutomationJobModel> records;

  final AutomationWeeklySummary? weekly;

  int sweepRuns = 0;

  @override
  Future<List<AutomationJobModel>> jobs({
    AutomationJobStatus? status,
    int? applicationId,
  }) async {
    if (status == null) {
      return records;
    }

    return records
        .where((AutomationJobModel job) => job.status == status)
        .toList(growable: false);
  }

  @override
  Future<AutomationWeeklySummary?> weeklySummary() async => weekly;

  @override
  Future<AutomationSweepResult> runSweep({
    required AutomationSweepType type,
    AutomationFaultMode faultMode = AutomationFaultMode.none,
  }) async {
    sweepRuns += 1;

    return AutomationSweepResult.fromJson(<String, dynamic>{
      'type': type.wire,
      'scanned': 4,
      'enqueued': 2,
      'duplicates_skipped': 1,
      'dispatch': <String, dynamic>{
        'processed': 2,
        'succeeded': 2,
        'retry_scheduled': 0,
        'manual_review_required': 0,
      },
    });
  }
}

AutomationJobModel _job({
  int id = 1,
  String status = 'succeeded',
  int attempts = 1,
  String applicationReferenceCode = 'INT-2026-0005',
}) {
  return AutomationJobModel.fromJson(<String, dynamic>{
    'id': id,
    'correlation_id': 'corr-1234567890',
    'idempotency_key': 'idem-0001',
    'internship_application_id': 5,
    'application_reference_code': applicationReferenceCode,
    'automation_type': 'milestone',
    'automation_type_label': 'Milestone notification',
    'recipient_role': 'adviser',
    'recipient_identifier': 'EMP-ADV-001',
    'status': status,
    'attempts': attempts,
    'max_attempts': 3,
    'failure_class': status == 'manual_review_required' ? 'permanent' : 'transient',
    'last_error_code': status == 'manual_review_required' ? 422 : 503,
    'last_error_summary': 'Recipient account is inactive.',
    'guidance': 'Correct the recipient identifier and requeue the job.',
    'next_retry_at': null,
    'completed_at': '2026-11-17T01:00:00Z',
    'coordinator_remarks': null,
    'created_at': '2026-11-16T23:00:00Z',
  });
}

Future<void> _pumpScreen(
  WidgetTester tester,
  _FakeAutomationService service, {
  Size size = const Size(1280, 900),
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  final AuthController auth = AuthController(authService: _FakeAuthService());
  await auth.login('coordinator.demo@uls-test.edu.ph', 'Password123!');

  final AutomationMonitorController controller = AutomationMonitorController(
    service: service,
    canManage: true,
  );

  await controller.load();

  await tester.pumpWidget(
    MultiProvider(
      providers: <SingleChildWidget>[
        Provider<ApiClient>(create: (_) => ApiClient()),
        ChangeNotifierProvider<AuthController>.value(value: auth),
        ChangeNotifierProvider<AutomationMonitorController>.value(
          value: controller,
        ),
      ],
      child: MaterialApp(
        theme: AppTheme.dark,
        home: const Scaffold(body: AutomationMonitorScreen()),
      ),
    ),
  );

  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  testWidgets('AutomationMonitorScreen renders jobs, counters and guidance', (
    WidgetTester tester,
  ) async {
    final _FakeAutomationService service = _FakeAutomationService(<AutomationJobModel>[
      _job(id: 7, status: 'succeeded'),
      _job(id: 6, status: 'manual_review_required', attempts: 3),
    ], weekly: AutomationWeeklySummary.fromJson(<String, dynamic>{
      'week_key': '2026-W46',
      'week_start': '2026-11-16',
      'week_end': '2026-11-22',
      'counts': <String, dynamic>{
        'submitted': 4,
        'requirements_verified': 2,
        'succeeded': 1,
      },
      'total': 7,
      'generated_at': '2026-11-17T00:00:00Z',
      'job_id': 9,
    }));

    await _pumpScreen(tester, service);

    expect(find.text('Automation monitor'), findsOneWidget);
    expect(find.text('Total jobs'), findsOneWidget);
    expect(find.text('Milestone notification'), findsNWidgets(2));
    expect(find.text('Correct the recipient identifier and requeue the job.'), findsOneWidget);
    expect(find.text('Coordinator action required'), findsOneWidget);
    expect(find.text('Weekly automation summary'), findsOneWidget);
    expect(find.text('Week 2026-W46'), findsOneWidget);
    expect(find.byType(FilledButton), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('AutomationMonitorScreen filters jobs by status', (
    WidgetTester tester,
  ) async {
    final _FakeAutomationService service = _FakeAutomationService(<AutomationJobModel>[
      _job(id: 7, status: 'succeeded'),
      _job(id: 6, status: 'manual_review_required', attempts: 3),
    ]);

    await _pumpScreen(tester, service);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Succeeded (1)'));
    await tester.pumpAndSettle();

    expect(find.text('Succeeded (1)'), findsOneWidget);
    expect(find.text('Coordinator action required'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('AutomationMonitorScreen runs a controlled sweep', (
    WidgetTester tester,
  ) async {
    final _FakeAutomationService service = _FakeAutomationService(
      <AutomationJobModel>[_job(id: 7, status: 'succeeded')],
    );

    await _pumpScreen(tester, service);

    await tester.tap(find.widgetWithText(FilledButton, 'Run Controlled Sweep'));
    await tester.pumpAndSettle();

    expect(find.text('Run a controlled automation sweep'), findsOneWidget);

    await tester.tap(find.widgetWithText(FilledButton, 'Run Sweep'));
    await tester.pumpAndSettle();

    expect(service.sweepRuns, 1);
    expect(find.byType(SnackBar), findsOneWidget);
    expect(
      find.textContaining('sweep finished'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });
}