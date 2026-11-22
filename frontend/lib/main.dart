import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:provider/single_child_widget.dart';

import 'core/network/api_client.dart';
import 'core/theme/app_theme.dart';
import 'core/widgets/app_shell.dart';
import 'features/auth/login_screen.dart';
import 'services/api_contract_service.dart';
import 'services/application_service.dart';
import 'services/auth_service.dart';
import 'services/automation_service.dart';
import 'services/crypto_service.dart';
import 'services/security_service.dart';
import 'state/auth_controller.dart';

void main() {
  runApp(const InternshipMonitorApp());
}

class InternshipMonitorApp extends StatelessWidget {
  const InternshipMonitorApp({super.key});

  @override
  Widget build(BuildContext context) {
    final ApiClient apiClient = ApiClient();

    return MultiProvider(
      providers: <SingleChildWidget>[
        Provider<ApiClient>.value(value: apiClient),
        Provider<AuthService>(
          create: (_) => AuthService(apiClient),
        ),
        Provider<ApplicationService>(
          create: (_) => ApplicationService(apiClient),
        ),
        Provider<AutomationService>(
          create: (_) => AutomationService(apiClient),
        ),
        Provider<CryptoService>(create: (_) => CryptoService(apiClient)),
        Provider<SecurityService>(create: (_) => SecurityService(apiClient)),
        Provider<ApiContractService>(create: (_) => ApiContractService(apiClient)),
        ChangeNotifierProvider<AuthController>(
          create: (BuildContext context) =>
              AuthController(authService: context.read<AuthService>()),
        ),
      ],
      child: MaterialApp(
        title: 'Internship Application Monitor',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.dark,
        home: const _AuthGate(),
      ),
    );
  }
}

class _AuthGate extends StatelessWidget {
  const _AuthGate();

  @override
  Widget build(BuildContext context) {
    final bool authenticated = context.select<AuthController, bool>(
      (AuthController controller) => controller.isAuthenticated,
    );

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 220),
      child: authenticated
          ? const AppShell(key: ValueKey<String>('shell'))
          : const LoginScreen(key: ValueKey<String>('login')),
    );
  }
}