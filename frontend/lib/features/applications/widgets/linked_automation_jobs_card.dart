import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/automation_job_status_badge.dart';
import '../../../models/automation_job_model.dart';
import '../../../services/automation_service.dart';

class LinkedAutomationJobsCard extends StatefulWidget {
  const LinkedAutomationJobsCard({super.key, required this.applicationId});

  final int applicationId;

  @override
  State<LinkedAutomationJobsCard> createState() =>
      _LinkedAutomationJobsCardState();
}

class _LinkedAutomationJobsCardState extends State<LinkedAutomationJobsCard> {
  late Future<List<AutomationJobModel>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<AutomationJobModel>> _load() {
    return context.read<AutomationService>().jobs(
      applicationId: widget.applicationId,
    );
  }

  void _refresh() {
    setState(() => _future = _load());
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                const Icon(
                  Icons.auto_mode_rounded,
                  size: 19,
                  color: AppColors.secondary,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Linked automation jobs',
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                IconButton(
                  tooltip: 'Refresh linked automation jobs',
                  onPressed: _refresh,
                  icon: const Icon(Icons.refresh_rounded, size: 20),
                ),
              ],
            ),
            const SizedBox(height: 12),
            FutureBuilder<List<AutomationJobModel>>(
              future: _future,
              builder: (
                BuildContext context,
                AsyncSnapshot<List<AutomationJobModel>> snapshot,
              ) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return Row(
                    children: <Widget>[
                      const SizedBox(
                        width: 15,
                        height: 15,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                      const SizedBox(width: 10),
                      Text(
                        'Loading linked automation jobs...',
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  );
                }

                if (snapshot.hasError) {
                  return Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const Icon(
                        Icons.error_outline_rounded,
                        size: 16,
                        color: AppColors.warning,
                      ),
                      const SizedBox(width: 9),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              'Linked automation jobs could not be loaded. The '
                              'application itself is unaffected.',
                              style: theme.textTheme.bodySmall,
                            ),
                            const SizedBox(height: 6),
                            TextButton(
                              onPressed: _refresh,
                              child: const Text('Try again'),
                            ),
                          ],
                        ),
                      ),
                    ],
                  );
                }

                final List<AutomationJobModel> jobs =
                    snapshot.data ?? const <AutomationJobModel>[];

                if (jobs.isEmpty) {
                  return Text(
                    'No automation jobs are linked to this application yet. '
                    'Sweeps and milestone notifications appear here once the '
                    'scheduler enqueues them.',
                    style: theme.textTheme.bodySmall?.copyWith(height: 1.45),
                  );
                }

                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    for (int index = 0; index < jobs.length; index++) ...<Widget>[
                      if (index > 0) const SizedBox(height: 8),
                      _CompactJobRow(job: jobs[index]),
                    ],
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _CompactJobRow extends StatelessWidget {
  const _CompactJobRow({required this.job});

  final AutomationJobModel job;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surfaceAlt,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              AutomationJobStatusBadge(status: job.status, compact: true),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  job.automationTypeLabel,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Text(
                'Attempts ${job.attempts}/${job.maxAttempts}',
                style: theme.textTheme.bodySmall,
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            job.guidance,
            style: theme.textTheme.bodySmall?.copyWith(
              color: AppColors.textSecondary,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}