import 'package:flutter/material.dart';

import '../../theme/app_theme.dart';
import '../state_panel.dart';

class SessionExpiredStateView extends StatelessWidget {
  const SessionExpiredStateView({
    super.key,
    this.message = 'Your session has expired. Sign in again to continue working.',
    this.actionLabel = 'Back to sign in',
    this.onAction,
  });

  final String message;
  final String actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return StatePanel(
      icon: Icons.timer_off_rounded,
      title: 'Session expired',
      message: message,
      accent: AppColors.warning,
      actionLabel: actionLabel,
      onAction: onAction,
    );
  }
}