class DateFormatX {
  const DateFormatX._();

  static const List<String> _months = <String>[
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
  ];

  static String date(String? raw) {
    final value = DateTime.tryParse(raw ?? '');
    if (value == null) {
      return 'Not set';
    }
    return '${_months[value.month - 1]} ${value.day}, ${value.year}';
  }

  static String dateTime(String? raw) {
    final value = DateTime.tryParse(raw ?? '');
    if (value == null) {
      return 'Not set';
    }

    final local = value.toLocal();
    final hour24 = local.hour;
    final suffix = hour24 >= 12 ? 'PM' : 'AM';
    final hour12 = hour24 % 12 == 0 ? 12 : hour24 % 12;

    return '${_months[local.month - 1]} ${local.day}, ${local.year} '
        '${hour12.toString().padLeft(2, '0')}:'
        '${local.minute.toString().padLeft(2, '0')} $suffix';
  }

  static String dateInputValue(String? raw) {
    final value = DateTime.tryParse(raw ?? '');
    if (value == null) {
      return '';
    }
    return '${value.year.toString().padLeft(4, '0')}-'
        '${value.month.toString().padLeft(2, '0')}-'
        '${value.day.toString().padLeft(2, '0')}';
  }
}