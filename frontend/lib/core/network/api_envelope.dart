import '../utils/json_utils.dart';

class ApiEnvelope {
  const ApiEnvelope({
    required this.success,
    required this.message,
    this.data,
    this.errors,
  });

  factory ApiEnvelope.fromJson(Map<String, dynamic> json) {
    return ApiEnvelope(
      success: asBool(json['success']),
      message: stringOr(json['message'], 'Request completed.'),
      data: json['data'],
      errors: _parseErrors(json['errors']),
    );
  }

  final bool success;
  final String message;
  final Object? data;
  final Map<String, List<String>>? errors;

  Map<String, dynamic> dataAsMap() => asMap(data);

  List<Object?> dataAsList() => asList(data);

  static Map<String, List<String>>? _parseErrors(Object? raw) {
    final map = asMap(raw);

    if (map.isEmpty) {
      return null;
    }

    final parsed = map.map((key, value) {
      final messages = value is List
          ? value.map((item) => item.toString()).toList(growable: false)
          : <String>[value.toString()];

      return MapEntry(key, messages);
    });

    return parsed.isEmpty ? null : parsed;
  }
}