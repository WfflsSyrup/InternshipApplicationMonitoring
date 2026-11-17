class UserModel {
  const UserModel({
    required this.id,
    required this.studentOrEmployeeId,
    required this.name,
    required this.email,
    required this.role,
    this.departmentOrCompany,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: _readInt(json, 'id'),
      studentOrEmployeeId: _readString(json, 'student_or_employee_id'),
      name: _readString(json, 'name'),
      email: _readString(json, 'email'),
      role: _readString(json, 'role'),
      departmentOrCompany: _readNullableString(json, 'department_or_company'),
    );
  }

  static const String roleStudent = 'student';
  static const String roleAdviser = 'adviser';
  static const String roleCoordinator = 'coordinator';
  static const String rolePartnerReviewer = 'partner_reviewer';

  final int id;
  final String studentOrEmployeeId;
  final String name;
  final String email;
  final String role;
  final String? departmentOrCompany;

  bool get isStudent => role == roleStudent;

  bool get isAdviser => role == roleAdviser;

  bool get isCoordinator => role == roleCoordinator;

  bool get isPartnerReviewer => role == rolePartnerReviewer;

  String get roleLabel {
    return switch (role) {
      roleStudent => 'Student',
      roleAdviser => 'Adviser',
      roleCoordinator => 'Coordinator',
      rolePartnerReviewer => 'Partner Reviewer',
      _ => role.isEmpty ? 'User' : role,
    };
  }

  String get initials {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .toList();

    if (parts.isEmpty) {
      return '?';
    }

    if (parts.length == 1) {
      final single = parts.first;
      return (single.length <= 2 ? single : single.substring(0, 2)).toUpperCase();
    }

    return '${parts.first.substring(0, 1)}${parts.last.substring(0, 1)}'
        .toUpperCase();
  }

  static int _readInt(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static String _readString(Map<String, dynamic> json, String key) {
    return json[key]?.toString() ?? '';
  }

  static String? _readNullableString(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value == null) {
      return null;
    }
    final text = value.toString().trim();
    return text.isEmpty ? null : text;
  }
}