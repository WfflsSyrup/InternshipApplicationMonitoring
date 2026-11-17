import 'package:flutter/material.dart';

import '../../models/automation_job_model.dart';

class AutomationJobStatusBadge extends StatelessWidget {
  const AutomationJobStatusBadge({
    super.key,
    required this.status,
    this.compact = false,
  });

  final AutomationJobStatus status;

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final Color accent = status.color;

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 10 : 12,
        vertical: compact ? 4 : 6,
      ),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: accent.withValues(alpha: 0.5)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(color: accent, shape: BoxShape.circle),
          ),
          const SizedBox(width: 7),
          Text(
            status.label,
            style: TextStyle(
              color: accent,
              fontSize: compact ? 11.5 : 12.5,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }
}