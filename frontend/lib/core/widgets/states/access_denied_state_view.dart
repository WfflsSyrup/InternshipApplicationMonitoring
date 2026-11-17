import 'package:flutter/material.dart';

import '../../theme/app_theme.dart';
import '../state_panel.dart';

class AccessDeniedStateView extends StatelessWidget {
  const AccessDeniedStateView({
    super.key,
    this.message =
        'Access Denied — your role does not have permission to view or modify this record.',
    this.actionLabel,
    this.onAction,
  });

  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return StatePanel(
      icon: Icons.gpp_bad_rounded,
      title: 'Access Denied',
      message: message,
      accent: AppColors.danger,
      actionLabel: actionLabel,
      onAction: onAction,
    );
  }
}