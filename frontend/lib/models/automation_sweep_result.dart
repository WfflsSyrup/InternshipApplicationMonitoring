import '../core/utils/json_utils.dart';
import '../models/automation_job_model.dart';

class AutomationDispatchSummary {
  const AutomationDispatchSummary({
    required this.processed,
    required this.succeeded,
    required this.retryScheduled,
    required this.manualReviewRequired,
  });

  factory AutomationDispatchSummary.fromJson(Map<String, dynamic> json) {
    return AutomationDispatchSummary(
      processed: asInt(json['processed']),
      succeeded: asInt(json['succeeded']),
      retryScheduled: asInt(json['retry_scheduled']),
      manualReviewRequired: asInt(json['manual_review_required']),
    );
  }

  final int processed;

  final int succeeded;

  final int retryScheduled;

  final int manualReviewRequired;

  String get summary =>
      '$succeeded succeeded · $retryScheduled retrying · '
      '$manualReviewRequired need review';
}

class AutomationSweepResult {
  const AutomationSweepResult({
    required this.type,
    required this.scanned,
    required this.enqueued,
    required this.duplicatesSkipped,
    required this.dispatch,
  });

  factory AutomationSweepResult.fromJson(Map<String, dynamic> json) {
    return AutomationSweepResult(
      type: AutomationSweepType.fromWire(asString(json['type'])),
      scanned: asInt(json['scanned']),
      enqueued: asInt(json['enqueued']),
      duplicatesSkipped: asInt(json['duplicates_skipped']),
      dispatch: AutomationDispatchSummary.fromJson(
        asMap(json['dispatch']),
      ),
    );
  }

  final AutomationSweepType type;

  final int scanned;

  final int enqueued;

  final int duplicatesSkipped;

  final AutomationDispatchSummary dispatch;
}

class AutomationWeeklySummary {
  const AutomationWeeklySummary({
    required this.weekKey,
    required this.counts,
    required this.total,
    this.weekStart,
    this.weekEnd,
    this.generatedAt,
    this.jobId,
  });

  factory AutomationWeeklySummary.fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> rawCounts = asMap(json['counts']);

    final Map<String, int> counts = <String, int>{
      for (final MapEntry<String, dynamic> entry in rawCounts.entries)
        entry.key: asInt(entry.value),
    };

    return AutomationWeeklySummary(
      weekKey: stringOr(json['week_key'], '—'),
      weekStart: asString(json['week_start']),
      weekEnd: asString(json['week_end']),
      counts: counts,
      total: asInt(json['total']),
      generatedAt: asString(json['generated_at']),
      jobId: json['job_id'] == null ? null : asInt(json['job_id']),
    );
  }

  final String weekKey;

  final String? weekStart;

  final String? weekEnd;

  final Map<String, int> counts;

  final int total;

  final String? generatedAt;

  final int? jobId;

  List<MapEntry<String, int>> get sortedCounts {
    final List<MapEntry<String, int>> entries = counts.entries.toList(
      growable: false,
    );

    entries.sort((MapEntry<String, int> a, MapEntry<String, int> b) {
      return b.value.compareTo(a.value);
    });

    return entries;
  }

  bool get isEmpty => total == 0 && counts.isEmpty;
}