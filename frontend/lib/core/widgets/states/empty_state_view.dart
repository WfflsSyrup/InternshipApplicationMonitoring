import 'package:flutter/material.dart';

import '../../theme/app_theme.dart';
import '../state_panel.dart';

class EmptyStateView extends StatelessWidget {
  const EmptyStateView({
    super.key,
    this.icon = Icons.inbox_rounded,
    this.title = 'No records found',
    this.message = 'There is nothing to display yet.',
    this.actionLabel,
    this.onAction,
    this.accent = AppColors.info,
  });

  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return StatePanel(
      icon: icon,
      title: title,
      message: message,
      accent: accent,
      actionLabel: actionLabel,
      onAction: onAction,
    );
  }
}