import 'package:flutter/material.dart';

import '../network/api_exception.dart';
import 'states/access_denied_state_view.dart';
import 'states/empty_state_view.dart';
import 'states/error_state_view.dart';
import 'states/loading_state_view.dart';
import 'states/session_expired_state_view.dart';

class AsyncStateView extends StatelessWidget {
  const AsyncStateView({
    super.key,
    required this.loading,
    required this.error,
    required this.builder,
    this.isEmpty = false,
    this.emptyBuilder,
    this.onRetry,
    this.onSessionExpired,
    this.loadingMessage,
    this.forbiddenMessage,
  });

  final bool loading;
  final ApiException? error;
  final WidgetBuilder builder;
  final bool isEmpty;
  final WidgetBuilder? emptyBuilder;
  final VoidCallback? onRetry;
  final VoidCallback? onSessionExpired;
  final String? loadingMessage;
  final String? forbiddenMessage;

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return LoadingStateView(message: loadingMessage ?? 'Loading data...');
    }

    final ApiException? currentError = error;

    if (currentError != null) {
      if (currentError.isForbidden) {
        return AccessDeniedStateView(
          message: forbiddenMessage ??
              'Access Denied — your role does not have permission to view this record.',
        );
      }

      if (currentError.isUnauthorized) {
        return SessionExpiredStateView(onAction: onSessionExpired);
      }

      return ErrorStateView(error: currentError, onRetry: onRetry);
    }

    if (isEmpty) {
      return emptyBuilder?.call(context) ?? const EmptyStateView();
    }

    return builder(context);
  }
}