import '../core/utils/json_utils.dart';

class CryptoKeyringTotals {
  const CryptoKeyringTotals({
    required this.envelopes,
    required this.verified,
    required this.rejected,
  });

  factory CryptoKeyringTotals.fromJson(Map<String, dynamic> json) {
    return CryptoKeyringTotals(
      envelopes: asInt(json['envelopes']),
      verified: asInt(json['verified']),
      rejected: asInt(json['rejected']),
    );
  }

  final int envelopes;

  final int verified;

  final int rejected;
}

class CryptoKeyringStatus {
  const CryptoKeyringStatus({
    required this.activeKid,
    required this.supportedKids,
    required this.cipherSuite,
    required this.signatureAlgorithm,
    required this.ivLengthBytes,
    required this.tagLengthBytes,
    required this.clockSkewSeconds,
    required this.tamperSimulationEnabled,
    required this.keyMaterialExposed,
    required this.clientKeysEmbedded,
    required this.totals,
  });

  factory CryptoKeyringStatus.fromJson(Map<String, dynamic> json) {
    return CryptoKeyringStatus(
      activeKid: stringOr(json['active_kid'], '—'),
      supportedKids: asStringList(json['supported_kids']),
      cipherSuite: stringOr(json['cipher_suite'], 'AES-256-GCM+HMAC-SHA256'),
      signatureAlgorithm: stringOr(json['signature_algorithm'], 'HMAC-SHA256'),
      ivLengthBytes: asInt(json['iv_length_bytes']),
      tagLengthBytes: asInt(json['tag_length_bytes']),
      clockSkewSeconds: asInt(json['clock_skew_seconds']),
      tamperSimulationEnabled: asBool(json['tamper_simulation_enabled']),
      keyMaterialExposed: asBool(json['key_material_exposed']),
      clientKeysEmbedded: asInt(json['client_keys_embedded']),
      totals: CryptoKeyringTotals.fromJson(asMap(json['totals'])),
    );
  }

  final String activeKid;

  final List<String> supportedKids;

  final String cipherSuite;

  final String signatureAlgorithm;

  final int ivLengthBytes;

  final int tagLengthBytes;

  final int clockSkewSeconds;

  final bool tamperSimulationEnabled;

  final bool keyMaterialExposed;

  final int clientKeysEmbedded;

  final CryptoKeyringTotals totals;

  String get supportedKidsLabel => supportedKids.isEmpty ? '—' : supportedKids.join(', ');

  String get replayWindowLabel => '±${clockSkewSeconds}s';

  String get ivLabel => '$ivLengthBytes bytes (96-bit)';

  String get tagLabel => '${tagLengthBytes * 8}-bit tag';

  bool get hasGraceKeys => supportedKids.length > 1;
}