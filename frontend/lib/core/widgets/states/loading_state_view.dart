import 'package:flutter/material.dart';

import '../../theme/app_theme.dart';

class LoadingStateView extends StatelessWidget {
  const LoadingStateView({super.key, this.message = 'Loading data...', this.icon});

  final String message;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 34),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                const SizedBox(
                  width: 34,
                  height: 34,
                  child: CircularProgressIndicator(strokeWidth: 2.6),
                ),
                const SizedBox(height: 18),
                Text(message, style: theme.textTheme.titleMedium),
                const SizedBox(height: 6),
                Text(
                  'Fetching the latest records from the internship monitor API.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class LoadingTile extends StatelessWidget {
  const LoadingTile({super.key, this.label = 'Loading...', this.color = AppColors.primary});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        SizedBox(
          width: 16,
          height: 16,
          child: CircularProgressIndicator(strokeWidth: 2, color: color),
        ),
        const SizedBox(width: 10),
        Flexible(
          child: Text(label, style: Theme.of(context).textTheme.bodySmall),
        ),
      ],
    );
  }
}