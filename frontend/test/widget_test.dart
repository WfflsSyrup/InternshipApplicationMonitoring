import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

import 'package:internship_monitor/core/network/api_client.dart';
import 'package:internship_monitor/core/theme/app_theme.dart';
import 'package:internship_monitor/features/auth/login_screen.dart';
import 'package:internship_monitor/services/auth_service.dart';
import 'package:internship_monitor/state/auth_controller.dart';

void main() {
  testWidgets('Login screen renders only the credential sign-in form', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      ChangeNotifierProvider<AuthController>(
        create: (_) => AuthController(authService: AuthService(ApiClient())),
        child: MaterialApp(
          theme: AppTheme.dark,
          home: const LoginScreen(),
        ),
      ),
    );

    expect(find.text('Sign in'), findsNWidgets(2));
    expect(find.widgetWithText(FilledButton, 'Sign in'), findsOneWidget);
    expect(find.byType(TextFormField), findsNWidgets(2));
  });

  testWidgets('Login screen has no one-tap role sign-in shortcuts', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      ChangeNotifierProvider<AuthController>(
        create: (_) => AuthController(authService: AuthService(ApiClient())),
        child: MaterialApp(
          theme: AppTheme.dark,
          home: const LoginScreen(),
        ),
      ),
    );

    expect(find.byType(ActionChip), findsNothing);
    expect(find.byType(Chip), findsNothing);
    expect(find.text('Controlled demo accounts'), findsNothing);
    expect(find.text('Coordinator'), findsNothing);
    expect(find.text('Partner Reviewer'), findsNothing);
  });
}