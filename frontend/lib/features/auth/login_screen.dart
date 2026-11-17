import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/states/error_state_view.dart';
import '../../state/auth_controller.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  final TextEditingController _emailController = TextEditingController();

  final TextEditingController _passwordController = TextEditingController();

  final FocusNode _emailFocus = FocusNode();

  final FocusNode _passwordFocus = FocusNode();

  bool _obscured = true;

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();

    final AuthController auth = context.read<AuthController>();

    await auth.login(
      _emailController.text.trim(),
      _passwordController.text,
    );
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _emailFocus.dispose();
    _passwordFocus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final AuthController auth = context.watch<AuthController>();
    final Map<String, String?> fieldErrors = <String, String?>{
      for (final MapEntry<String, List<String>> entry
          in (auth.error?.fieldErrors ?? const <String, List<String>>{}).entries)
        entry.key: entry.value.isEmpty ? null : entry.value.first,
    };

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 900),
              child: LayoutBuilder(
                builder: (BuildContext context, BoxConstraints constraints) {
                  final bool wide = constraints.maxWidth >= 760;

                  final Widget form = _LoginForm(
                    formKey: _formKey,
                    emailController: _emailController,
                    passwordController: _passwordController,
                    emailFocus: _emailFocus,
                    passwordFocus: _passwordFocus,
                    obscured: _obscured,
                    onToggleObscure: () =>
                        setState(() => _obscured = !_obscured),
                    auth: auth,
                    fieldErrors: fieldErrors,
                    onSubmit: () {
                      if (_formKey.currentState?.validate() ?? false) {
                        _submit();
                      }
                    },
                  );

                  if (!wide) {
                    return form;
                  }

                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Expanded(child: _BrandPanel(theme: theme)),
                      const SizedBox(width: 32),
                      Expanded(child: form),
                    ],
                  );
                },
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _BrandPanel extends StatelessWidget {
  const _BrandPanel({required this.theme});

  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: <Color>[AppColors.primary, AppColors.secondary],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(18),
            ),
            child: const Icon(
              Icons.work_outline_rounded,
              color: AppColors.onPrimary,
              size: 30,
            ),
          ),
          const SizedBox(height: 22),
          Text('Internship Application Monitor', style: theme.textTheme.displaySmall),
          const SizedBox(height: 12),
          Text(
            'One bounded workflow for submitting, verifying, endorsing, '
            'scheduling interviews, and confirming internship placements '
            'across students, advisers, coordinators, and partner reviewers.',
            style: theme.textTheme.bodyMedium?.copyWith(height: 1.55),
          ),
        ],
      ),
    );
  }
}

class _LoginForm extends StatelessWidget {
  const _LoginForm({
    required this.formKey,
    required this.emailController,
    required this.passwordController,
    required this.emailFocus,
    required this.passwordFocus,
    required this.obscured,
    required this.onToggleObscure,
    required this.auth,
    required this.fieldErrors,
    required this.onSubmit,
  });

  final GlobalKey<FormState> formKey;
  final TextEditingController emailController;
  final TextEditingController passwordController;
  final FocusNode emailFocus;
  final FocusNode passwordFocus;
  final bool obscured;
  final VoidCallback onToggleObscure;
  final AuthController auth;
  final Map<String, String?> fieldErrors;
  final VoidCallback onSubmit;

  String? _required(String? value, String message) {
    if (value == null || value.trim().isEmpty) {
      return message;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool sessionExpired = auth.sessionExpired;

    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text('Sign in', style: theme.textTheme.headlineSmall),
            const SizedBox(height: 6),
            Text(
              'Use the internship monitor account issued by your coordinator.',
              style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
            ),
            const SizedBox(height: 20),
            if (sessionExpired)
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.warning.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: AppColors.warning.withValues(alpha: 0.45),
                    ),
                  ),
                  child: Row(
                    children: <Widget>[
                      const Icon(
                        Icons.timer_off_rounded,
                        color: AppColors.warning,
                        size: 20,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Session expired — please sign in again to continue.',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            Form(
              key: formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  TextFormField(
                    controller: emailController,
                    focusNode: emailFocus,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    autofocus: false,
                    autofillHints: const <String>[AutofillHints.email],
                    onFieldSubmitted: (_) => passwordFocus.requestFocus(),
                    decoration: InputDecoration(
                      labelText: 'Email address',
                      prefixIcon: const Icon(Icons.alternate_email_rounded),
                      errorText: fieldErrors['email'],
                    ),
                    validator: (String? value) =>
                        _required(value, 'Email address is required.'),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: passwordController,
                    focusNode: passwordFocus,
                    obscureText: obscured,
                    textInputAction: TextInputAction.done,
                    autofillHints: const <String>[AutofillHints.password],
                    onFieldSubmitted: (_) => onSubmit(),
                    decoration: InputDecoration(
                      labelText: 'Password',
                      prefixIcon: const Icon(Icons.lock_outline_rounded),
                      errorText: fieldErrors['password'],
                      suffixIcon: IconButton(
                        onPressed: onToggleObscure,
                        icon: Icon(
                          obscured
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                        ),
                        tooltip: obscured ? 'Show password' : 'Hide password',
                      ),
                    ),
                    validator: (String? value) =>
                        _required(value, 'Password is required.'),
                  ),
                ],
              ),
            ),
            if (auth.error != null && fieldErrors.isEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    ErrorStateView.describe(auth.error!).message,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.danger,
                    ),
                  ),
                ),
              ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: auth.busy ? null : onSubmit,
              child: auth.busy
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: AppColors.onPrimary,
                      ),
                    )
                  : const Text('Sign in'),
            ),
          ],
        ),
      ),
    );
  }
}