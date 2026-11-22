import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:provider/single_child_widget.dart';

import 'package:internship_monitor/core/network/api_client.dart';
import 'package:internship_monitor/core/theme/app_theme.dart';
import 'package:internship_monitor/core/widgets/app_shell.dart';
import 'package:internship_monitor/models/user_model.dart';
import 'package:internship_monitor/services/api_contract_service.dart';
import 'package:internship_monitor/services/application_service.dart';
import 'package:internship_monitor/services/auth_service.dart';
import 'package:internship_monitor/services/automation_service.dart';
import 'package:internship_monitor/services/crypto_service.dart';
import 'package:internship_monitor/services/security_service.dart';
import 'package:internship_monitor/state/auth_controller.dart';

class _FakeAuthService extends AuthService {
  _FakeAuthService() : super(ApiClient());

  @override
  Future<UserModel> login(String email, String password) async {
    return UserModel(
      id: 1,
      studentOrEmployeeId: '2026-00001',
      name: 'Ana Dela Cruz',
      email: email,
      role: UserModel.roleStudent,
      departmentOrCompany: 'BSIT Department',
    );
  }
}

Future<void> _pumpShell(WidgetTester tester, Size size) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  final AuthController auth = AuthController(authService: _FakeAuthService());
  await auth.login('student1.demo@uls-test.edu.ph', 'Password123!');

  await tester.pumpWidget(
    MultiProvider(
      providers: <SingleChildWidget>[
        Provider<ApiClient>(create: (_) => ApiClient()),
        Provider<AuthService>(create: (_) => _FakeAuthService()),
        Provider<CryptoService>(create: (_) => CryptoService(ApiClient())),
        Provider<SecurityService>(create: (_) => SecurityService(ApiClient())),
        Provider<AutomationService>(create: (_) => AutomationService(ApiClient())),
        Provider<ApiContractService>(create: (_) => ApiContractService(ApiClient())),
        Provider<ApplicationService>(
          create: (BuildContext context) =>
              ApplicationService(context.read<ApiClient>()),
        ),
        ChangeNotifierProvider<AuthController>.value(value: auth),
      ],
      child: MaterialApp(
        theme: AppTheme.dark,
        home: const AppShell(),
      ),
    ),
  );

  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  testWidgets('AppShell lays out with a navigation rail on wide screens', (
    WidgetTester tester,
  ) async {
    await _pumpShell(tester, const Size(1280, 900));

    expect(find.byType(NavigationRail), findsOneWidget);
    expect(find.byType(Drawer), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('AppShell lays out with a drawer on narrow screens', (
    WidgetTester tester,
  ) async {
    await _pumpShell(tester, const Size(420, 900));

    expect(find.byType(NavigationRail), findsNothing);
    expect(find.byType(AppBar), findsOneWidget);
    expect(find.text('Dashboard'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}