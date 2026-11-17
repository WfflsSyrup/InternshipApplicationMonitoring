import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../models/application_model.dart';

class ApplicationTable extends StatelessWidget {
  const ApplicationTable({
    super.key,
    required this.applications,
    required this.onOpen,
    this.onEdit,
  });

  final List<ApplicationModel> applications;
  final ValueChanged<ApplicationModel> onOpen;
  final ValueChanged<ApplicationModel>? onEdit;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: ConstrainedBox(
          constraints: BoxConstraints(
            minWidth: MediaQuery.sizeOf(context).width - 420,
          ),
          child: DataTable(
            headingRowColor: WidgetStatePropertyAll<Color>(AppColors.surfaceAlt),
            headingTextStyle: Theme.of(context).textTheme.labelLarge,
            dataRowMinHeight: 58,
            dataRowMaxHeight: 72,
            dividerThickness: 1,
            columns: const <DataColumn>[
              DataColumn(label: Text('Reference')),
              DataColumn(label: Text('Host company / Role')),
              DataColumn(label: Text('Status')),
              DataColumn(label: Text('Requirements')),
              DataColumn(label: Text('Start date')),
              DataColumn(label: Text('Actions')),
            ],
            rows: applications
                .map(
                  (ApplicationModel application) => DataRow(
                    onSelectChanged: (_) => onOpen(application),
                    cells: <DataCell>[
                      DataCell(
                        Text(
                          application.referenceCode,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                      DataCell(
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            Text(application.hostCompanyDisplay),
                            Text(
                              application.targetRoleDisplay,
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                      DataCell(StatusBadge(status: application.status, compact: true)),
                      DataCell(
                        Row(
                          children: <Widget>[
                            Icon(
                              application.requirementsComplete
                                  ? Icons.check_circle_rounded
                                  : Icons.pending_actions_rounded,
                              size: 16,
                              color: application.requirementsComplete
                                  ? AppColors.secondary
                                  : AppColors.warning,
                            ),
                            const SizedBox(width: 6),
                            Text(application.requirementsSummary),
                          ],
                        ),
                      ),
                      DataCell(Text(application.startLabel)),
                      DataCell(
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            IconButton(
                              tooltip: 'Open application',
                              onPressed: () => onOpen(application),
                              icon: const Icon(Icons.open_in_new_rounded, size: 19),
                            ),
                            if (onEdit != null && application.canStudentEdit)
                              IconButton(
                                tooltip: 'Edit application',
                                onPressed: () => onEdit!(application),
                                icon: const Icon(Icons.edit_outlined, size: 19),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
                )
                .toList(growable: false),
          ),
        ),
      ),
    );
  }
}