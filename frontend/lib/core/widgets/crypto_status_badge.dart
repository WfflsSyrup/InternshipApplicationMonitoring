import 'package:flutter/material.dart';

import '../../models/crypto_envelope_model.dart';

class CryptoStatusBadge extends StatelessWidget {
  const CryptoStatusBadge({
    super.key,
    required this.status,
    this.compact = false,
  });

  final CryptoVerificationStatus status;

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
          Icon(
            status.isVerified ? Icons.verified_user_rounded : Icons.gpp_bad_rounded,
            size: compact ? 12 : 13,
            color: accent,
          ),
          const SizedBox(width: 6),
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