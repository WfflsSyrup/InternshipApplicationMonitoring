Map<String, dynamic> asMap(Object? value) {
  if (value is Map<String, dynamic>) {
    return value;
  }

  if (value is Map) {
    return value.map((key, item) => MapEntry(key.toString(), item));
  }

  return const <String, dynamic>{};
}

List<Object?> asList(Object? value) {
  if (value is List) {
    return value;
  }

  return const <Object?>[];
}

String? asString(Object? value) {
  if (value == null) {
    return null;
  }

  return value.toString();
}

String stringOr(Object? value, [String fallback = '']) {
  return asString(value) ?? fallback;
}

int asInt(Object? value) {
  if (value is int) {
    return value;
  }

  if (value is num) {
    return value.toInt();
  }

  return int.tryParse(asString(value) ?? '') ?? 0;
}

bool asBool(Object? value) {
  if (value is bool) {
    return value;
  }

  if (value is num) {
    return value != 0;
  }

  return asString(value)?.toLowerCase() == 'true';
}

Map<String, bool> asBoolMap(Object? value) {
  return asMap(value).map((key, item) => MapEntry(key, asBool(item)));
}

List<String> asStringList(Object? value) {
  if (value is! List) {
    return const <String>[];
  }

  return value
      .whereType<Object>()
      .map((item) => item.toString())
      .where((item) => item.isNotEmpty)
      .toList(growable: false);
}