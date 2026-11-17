import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../models/crypto_envelope_model.dart';
import '../models/crypto_keyring_model.dart';
import '../models/crypto_simulation_result.dart';
import '../services/crypto_service.dart';

class CryptoIntegrationController extends ChangeNotifier {
  CryptoIntegrationController({
    required this.service,
    required this.canManage,
  });

  final CryptoService service;

  final bool canManage;

  bool _loading = true;

  bool _dispatching = false;

  bool _runningSimulation = false;

  ApiException? _loadError;

  ApiException? _actionError;

  String? _successMessage;

  CryptoVerificationStatus? _statusFilter;

  CryptoDirection _directionFilter = CryptoDirection.unknown;

  List<CryptoEnvelopeModel> _envelopes = const <CryptoEnvelopeModel>[];

  CryptoKeyringStatus? _keyring;

  CryptoSimulationResult? _lastSimulation;

  bool get loading => _loading;

  bool get dispatching => _dispatching;

  bool get runningSimulation => _runningSimulation;

  ApiException? get loadError => _loadError;

  ApiException? get actionError => _actionError;

  String? get successMessage => _successMessage;

  CryptoVerificationStatus? get statusFilter => _statusFilter;

  CryptoDirection get directionFilter => _directionFilter;

  List<CryptoEnvelopeModel> get envelopes => _envelopes;

  CryptoKeyringStatus? get keyring => _keyring;

  CryptoSimulationResult? get lastSimulation => _lastSimulation;

  List<CryptoEnvelopeModel> get rejectedEnvelopes => _envelopes
      .where((CryptoEnvelopeModel envelope) => envelope.isRejected)
      .toList(growable: false);

  int get verifiedCount => _envelopes
      .where((CryptoEnvelopeModel envelope) => envelope.status.isVerified)
      .length;

  Future<void> load() async {
    _loading = true;
    _loadError = null;
    notifyListeners();

    try {
      _envelopes = await service.envelopes(
        status: _statusFilter,
        direction: _directionFilter,
      );
      _keyring = await _loadKeyring();
    } on ApiException catch (error) {
      _loadError = error;
    } catch (error) {
      _loadError = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while loading the crypto audit trail: $error',
      );
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> setStatusFilter(CryptoVerificationStatus? status) async {
    if (_statusFilter == status) {
      return;
    }

    _statusFilter = status;
    notifyListeners();

    await load();
  }

  Future<void> setDirectionFilter(CryptoDirection direction) async {
    if (_directionFilter == direction) {
      return;
    }

    _directionFilter = direction;
    notifyListeners();

    await load();
  }

  Future<CryptoEnvelopeModel?> dispatchPacket({
    required int applicationId,
    String? remarks,
  }) async {
    if (_dispatching) {
      return null;
    }

    _dispatching = true;
    _actionError = null;
    _successMessage = null;
    notifyListeners();

    try {
      final CryptoEnvelopeModel envelope = await service.dispatchPartnerPacket(
        applicationId: applicationId,
        remarks: remarks,
      );

      _successMessage =
          'Packet sealed for envelope #${envelope.id} · key ${envelope.keyId} · '
          '${envelope.cipherSuite}.';

      await load();

      return envelope;
    } on ApiException catch (error) {
      _actionError = error;
      return null;
    } catch (error) {
      _actionError = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while sealing the packet: $error',
      );
      return null;
    } finally {
      _dispatching = false;
      notifyListeners();
    }
  }

  Future<CryptoSimulationResult?> runSimulation({
    required CryptoSimulationMode mode,
    String? referenceCode,
    String? outcome,
  }) async {
    if (_runningSimulation) {
      return null;
    }

    _runningSimulation = true;
    _actionError = null;
    _successMessage = null;
    _lastSimulation = null;
    notifyListeners();

    try {
      final CryptoSimulationResult result = await service.runSimulation(
        mode: mode,
        referenceCode: referenceCode,
        outcome: outcome,
      );

      _lastSimulation = result;

      await load();

      return result;
    } on ApiException catch (error) {
      _actionError = error;
      return null;
    } catch (error) {
      _actionError = ApiException(
        kind: ApiErrorKind.unknown,
        message: 'Unexpected error while running the verification lab: $error',
      );
      return null;
    } finally {
      _runningSimulation = false;
      notifyListeners();
    }
  }

  Future<CryptoKeyringStatus?> _loadKeyring() async {
    try {
      return await service.keyringStatus();
    } on ApiException {
      return null;
    }
  }

  void clearActionError() {
    if (_actionError == null) {
      return;
    }

    _actionError = null;
    notifyListeners();
  }

  void clearSuccess() {
    if (_successMessage == null) {
      return;
    }

    _successMessage = null;
    notifyListeners();
  }

  void clearSimulation() {
    if (_lastSimulation == null) {
      return;
    }

    _lastSimulation = null;
    notifyListeners();
  }
}