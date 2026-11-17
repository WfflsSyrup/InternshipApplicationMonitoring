import 'package:flutter/material.dart';

import '../../network/api_exception.dart';
import '../../theme/app_theme.dart';
import '../state_panel.dart';

class ErrorPresentation {
  const ErrorPresentation({
    required this.icon,
    required this.title,
    required this.message,
    required this.accent,
  });

  final IconData icon;
  final String title;
  final String message;
  final Color accent;
}

class ErrorStateView extends StatelessWidget {
  const ErrorStateView({
    super.key,
    required this.error,
    this.onRetry,
    this.retryLabel = 'Retry Connection',
  });

  final ApiException error;
  final VoidCallback? onRetry;
  final String retryLabel;

  static ErrorPresentation describe(ApiException error) {
    return switch (error.kind) {
      ApiErrorKind.network => const ErrorPresentation(
        icon: Icons.cloud_off_rounded,
        title: 'Cannot reach the backend API',
        message:
            'The internship monitor service could not be reached. Confirm that the Laravel API server is running and that API_BASE_URL points to it.',
        accent: AppColors.danger,
      ),
      ApiErrorKind.timeout => const ErrorPresentation(
        icon: Icons.timer_off_rounded,
        title: 'The backend API timed out',
        message:
            'The internship monitor service did not answer in time. Retry the request to reconnect.',
        accent: AppColors.warning,
      ),
      ApiErrorKind.unavailable => const ErrorPresentation(
        icon: Icons.report_gmailerrorred_rounded,
        title: 'Service temporarily unavailable',
        message:
            'The internship monitor service is temporarily unavailable. Please retry shortly.',
        accent: AppColors.warning,
      ),
      ApiErrorKind.server => const ErrorPresentation(
        icon: Icons.report_gmailerrorred_rounded,
        title: 'Service temporarily unavailable',
        message:
            'The backend reported a temporary failure. No technical details are shown for security reasons. Please retry.',
        accent: AppColors.danger,
      ),
      ApiErrorKind.unauthorized => const ErrorPresentation(
        icon: Icons.lock_outline_rounded,
        title: 'Session expired',
        message: 'Your session is no longer active. Sign in again to continue.',
        accent: AppColors.warning,
      ),
      ApiErrorKind.forbidden => const ErrorPresentation(
        icon: Icons.block_rounded,
        title: 'Access denied',
        message: 'Your role does not allow this action.',
        accent: AppColors.danger,
      ),
      ApiErrorKind.notFound => const ErrorPresentation(
        icon: Icons.search_off_rounded,
        title: 'Record not found',
        message: 'The requested record does not exist or is no longer available.',
        accent: AppColors.warning,
      ),
      ApiErrorKind.conflict => const ErrorPresentation(
        icon: Icons.lock_clock_rounded,
        title: 'Replay protection rejected the request',
        message:
            'This request reuses a nonce that was already processed, so it was '
            'rejected before decryption. Re-issue the signed request with a '
            'fresh nonce and timestamp.',
        accent: AppColors.warning,
      ),
      ApiErrorKind.validation => ErrorPresentation(
        icon: Icons.error_outline_rounded,
        title: 'Some fields need attention',
        message: error.message,
        accent: AppColors.warning,
      ),
      ApiErrorKind.unknown => const ErrorPresentation(
        icon: Icons.error_outline_rounded,
        title: 'Something went wrong',
        message: 'An unexpected error occurred while processing the request.',
        accent: AppColors.danger,
      ),
    };
  }

  @override
  Widget build(BuildContext context) {
    final ErrorPresentation presentation = describe(error);

    return StatePanel(
      icon: presentation.icon,
      title: presentation.title,
      message: presentation.message,
      accent: presentation.accent,
      actionLabel: onRetry == null ? null : retryLabel,
      onAction: onRetry,
    );
  }
}