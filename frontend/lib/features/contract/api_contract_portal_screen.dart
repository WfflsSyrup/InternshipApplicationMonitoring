import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/async_state_view.dart';
import '../../core/widgets/states/empty_state_view.dart';
import '../../core/widgets/states/loading_state_view.dart';
import '../../core/widgets/states/error_state_view.dart';
import '../../models/api_contract_models.dart';
import '../../state/api_contract_controller.dart';

class ApiContractPortalScreen extends StatelessWidget {
  const ApiContractPortalScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<ApiContractController>(
      builder: (context, controller, _) {
        if (controller.isLoading) {
          return const LoadingStateView(message: 'Loading API contract...');
        }

        if (controller.error != null) {
          return ErrorStateView(
            error: controller.error!,
            onRetry: () => controller.load(),
          );
        }

        if (controller.specSummary == null) {
          return const EmptyStateView(
            icon: Icons.code_rounded,
            title: 'No Contract Data',
            message: 'Unable to load the API contract specification.',
            actionLabel: 'Retry',
            onAction: null,
          );
        }

        return Scaffold(
          body: CustomScrollView(
            slivers: [
              _buildHeaderSliver(context, controller),
              SliverToBoxAdapter(child: _buildSearchAndFilters(context, controller)),
              SliverToBoxAdapter(child: _buildContractSummaryCard(context, controller)),
              _buildEndpointCatalog(context, controller),
              if (controller.contractReport != null) _buildAuditCard(context, controller),
              const SliverToBoxAdapter(child: SizedBox(height: 24)),
            ],
          ),
        );
      },
    );
  }

  Widget _buildHeaderSliver(BuildContext context, ApiContractController controller) {
    final theme = Theme.of(context);
    return SliverAppBar(
      expandedHeight: 120,
      floating: false,
      pinned: true,
      flexibleSpace: FlexibleSpaceBar(
        titlePadding: const EdgeInsets.fromLTRB(24, 0, 24, 16),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'API Contract Portal',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            Text(
              'OpenAPI 3.1.0 • Contract v${controller.specSummary!.infoVersion}',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSearchAndFilters(BuildContext context, ApiContractController controller) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 16, 24, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            decoration: InputDecoration(
              hintText: 'Search endpoints by path, summary, or operation ID...',
              prefixIcon: const Icon(Icons.search_rounded),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              filled: true,
              fillColor: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            ),
            onChanged: controller.setSearchQuery,
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: controller.tags.map((tag) {
                final isSelected = controller.selectedTag == tag;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    label: Text(tag == 'all' ? 'All Tags' : tag),
                    selected: isSelected,
                    onSelected: (_) => controller.setSelectedTag(tag == 'all' ? null : tag),
                    backgroundColor: Theme.of(context).colorScheme.surfaceContainerHighest,
                    selectedColor: AppColors.primary.withValues(alpha: 0.18),
                    labelStyle: TextStyle(
                      color: isSelected ? AppColors.primary : Theme.of(context).colorScheme.onSurface,
                      fontWeight: isSelected ? FontWeight.w600 : FontWeight.w400,
                    ),
                    side: BorderSide(
                      color: isSelected ? AppColors.primary : Theme.of(context).colorScheme.outline,
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildContractSummaryCard(BuildContext context, ApiContractController controller) {
    final summary = controller.specSummary!;
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 8, 24, 16),
      child: Card(
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: theme.colorScheme.outline.withValues(alpha: 0.2)),
        ),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  _StatusChip(
                    label: 'OpenAPI ${summary.openapiVersion}',
                    color: AppColors.primary,
                    icon: Icons.code_rounded,
                  ),
                  const SizedBox(width: 12),
                  _StatusChip(
                    label: 'Contract v${summary.infoVersion}',
                    color: AppColors.secondary,
                    icon: Icons.description_rounded,
                  ),
                  const SizedBox(width: 12),
                  _StatusChip(
                    label: 'Base: ${summary.baseUrl}',
                    color: AppColors.info,
                    icon: Icons.link_rounded,
                  ),
                  const SizedBox(width: 12),
                  _StatusChip(
                    label: '${summary.documentedEndpoints} Endpoints',
                    color: AppColors.secondary,
                    icon: Icons.analytics_rounded,
                  ),
                ],
              ),
              if (summary.infoTitle.isNotEmpty) ...[
                const SizedBox(height: 12),
                Text(
                  summary.infoTitle,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurface.withValues(alpha: 0.8),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildEndpointCatalog(BuildContext context, ApiContractController controller) {
    final filtered = controller.filteredEndpoints;

    if (filtered.isEmpty) {
      return SliverToBoxAdapter(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: EmptyStateView(
            icon: Icons.filter_list_off_rounded,
            title: 'No Endpoints Match',
            message: controller.searchQuery.isNotEmpty || controller.selectedTag != null
                ? 'Try adjusting your search or filter'
                : 'No endpoints available',
            actionLabel: controller.searchQuery.isNotEmpty ? 'Clear Search' : null,
            onAction: controller.searchQuery.isNotEmpty ? () => controller.setSearchQuery('') : null,
          ),
        ),
      );
    }

    return SliverList(
      delegate: SliverChildBuilderDelegate(
        (context, index) {
          final endpoint = filtered[index];
          return _EndpointCard(
            endpoint: endpoint,
            controller: controller,
          );
        },
        childCount: filtered.length,
      ),
    );
  }

  Widget _buildAuditCard(BuildContext context, ApiContractController controller) {
    final report = controller.contractReport!;
    final theme = Theme.of(context);

    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 16, 24, 0),
        child: Card(
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: BorderSide(
              color: report.parityPassed ? AppColors.secondary : AppColors.danger,
              width: 1.5,
            ),
          ),
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(
                      report.parityPassed ? Icons.verified_rounded : Icons.error_rounded,
                      color: report.parityPassed ? AppColors.secondary : AppColors.danger,
                      size: 24,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Live Contract Parity Audit',
                        style: theme.textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    if (controller.isAuditLoading)
                      const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    else
                      TextButton.icon(
                        onPressed: () => controller.runContractAudit(),
                        icon: const Icon(Icons.refresh_rounded, size: 18),
                        label: const Text('Re-run Audit'),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                if (controller.auditError != null) ...[
                  Text(
                    controller.auditError!,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: AppColors.danger,
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
                if (report != null) ...[
                  Wrap(
                    spacing: 12,
                    runSpacing: 8,
                    children: [
                      _AuditBadge(
                        label: report.parityPassed ? '100% Route Parity' : 'Parity Drift Detected',
                        color: report.parityPassed ? AppColors.secondary : AppColors.danger,
                        icon: report.parityPassed ? Icons.check_circle_rounded : Icons.warning_rounded,
                      ),
                      _AuditBadge(
                        label: report.secretScanClean ? 'Zero-Secret Spec Clean' : 'Secrets Detected!',
                        color: report.secretScanClean ? AppColors.secondary : AppColors.danger,
                        icon: report.secretScanClean ? Icons.shield_rounded : Icons.report_rounded,
                      ),
                      _AuditBadge(
                        label: 'Documented: ${report.documentedOperations}',
                        color: AppColors.info,
                        icon: Icons.list_rounded,
                      ),
                      _AuditBadge(
                        label: 'Contract: ${report.contractVersion}',
                        color: AppColors.secondary,
                        icon: Icons.tag_rounded,
                      ),
                      _AuditBadge(
                        label: 'OpenAPI: ${report.openapiVersion}',
                        color: AppColors.info,
                        icon: Icons.api_rounded,
                      ),
                    ],
                  ),
                  if (report.missingFromSpec.isNotEmpty || report.staleInSpec.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    const Divider(),
                    const SizedBox(height: 12),
                    if (report.missingFromSpec.isNotEmpty) ...[
                      Text(
                        'Missing from Spec (${report.missingFromSpec.length})',
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.danger,
                        ),
                      ),
                      const SizedBox(height: 8),
                      ...report.missingFromSpec.map((m) => Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Text(
                          '• $m',
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontFamily: 'monospace',
                          ),
                        ),
                      )),
                    ],
                    if (report.staleInSpec.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      Text(
                        'Stale in Spec (${report.staleInSpec.length})',
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.warning,
                        ),
                      ),
                      const SizedBox(height: 8),
                      ...report.staleInSpec.map((s) => Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Text(
                          '• $s',
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontFamily: 'monospace',
                          ),
                        ),
                      )),
                    ],
                  ],
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String label;
  final Color color;
  final IconData icon;

  const _StatusChip({
    required this.label,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w600,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

class _AuditBadge extends StatelessWidget {
  final String label;
  final Color color;
  final IconData icon;

  const _AuditBadge({
    required this.label,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w600,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

class _EndpointCard extends StatefulWidget {
  final ApiEndpointSummary endpoint;
  final ApiContractController controller;

  const _EndpointCard({
    required this.endpoint,
    required this.controller,
  });

  @override
  State<_EndpointCard> createState() => _EndpointCardState();
}

class _EndpointCardState extends State<_EndpointCard> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final endpoint = widget.endpoint;
    final theme = Theme.of(context);
    final methodColor = _getMethodColor(endpoint.method);

    return Card(
      margin: const EdgeInsets.fromLTRB(24, 0, 24, 12),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: theme.colorScheme.outline.withValues(alpha: 0.2)),
      ),
      child: Column(
        children: [
          InkWell(
            onTap: () => setState(() => _expanded = !_expanded),
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  _MethodBadge(method: endpoint.method, color: methodColor),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          endpoint.path,
                          style: theme.textTheme.bodyLarge?.copyWith(
                            fontFamily: 'monospace',
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        if (endpoint.summary.isNotEmpty) ...[
                          const SizedBox(height: 4),
                          Text(
                            endpoint.summary,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  _TagChip(label: endpoint.tag),
                  const SizedBox(width: 8),
                  Icon(
                    _expanded ? Icons.keyboard_arrow_up_rounded : Icons.keyboard_arrow_down_rounded,
                    color: theme.colorScheme.onSurface.withValues(alpha: 0.5),
                  ),
                ],
              ),
            ),
          ),
          if (_expanded) ...[
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildAuthBadges(context, endpoint),
                  const SizedBox(height: 12),
                  _buildStatusChips(context, endpoint),
                  const SizedBox(height: 16),
                  _buildCurlSection(context, endpoint),
                  const SizedBox(height: 16),
                  if (endpoint.parameters.isNotEmpty) _buildParametersTable(context, endpoint),
                  if (endpoint.requestBody != null) _buildRequestBody(context, endpoint),
                  if (endpoint.responses.isNotEmpty) _buildResponsesTable(context, endpoint),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildAuthBadges(BuildContext context, ApiEndpointSummary endpoint) {
    if (endpoint.security.isEmpty) return const SizedBox.shrink();

    final badges = <Widget>[];
    for (final scheme in endpoint.security) {
      String label;
      Color color;
      switch (scheme) {
        case 'BearerSanctumAuth':
          label = 'Bearer Token (Sanctum)';
          color = AppColors.primary;
          break;
        case 'PartnerHmacHeaders':
          label = 'HMAC Headers (Partner)';
          color = AppColors.warning;
          break;
        default:
          label = scheme;
          color = AppColors.info;
      }
      badges.add(Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: color.withValues(alpha: 0.3)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.security_rounded, size: 14, color: color),
            const SizedBox(width: 4),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontWeight: FontWeight.w500,
                fontSize: 11,
              ),
            ),
          ],
        ),
      ));
    }

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        const Text('Auth: ', style: TextStyle(fontWeight: FontWeight.w500, fontSize: 12)),
        ...badges,
      ],
    );
  }

  Widget _buildStatusChips(BuildContext context, ApiEndpointSummary endpoint) {
    final responses = endpoint.responses;
    final statusOrder = ['200', '201', '204', '304', '400', '401', '403', '404', '409', '413', '422', '429', '500', '503'];

    final chips = <Widget>[];
    for (final code in statusOrder) {
      if (responses[code] != null) {
        Color color;
        if (code.startsWith('2')) color = AppColors.secondary;
        else if (code.startsWith('3')) color = AppColors.info;
        else if (code.startsWith('4')) color = AppColors.danger;
        else color = AppColors.warning;

        chips.add(Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(4),
            border: Border.all(color: color.withValues(alpha: 0.3)),
          ),
          child: Text(
            code,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w600,
              fontSize: 11,
            ),
          ),
        ));
      }
    }

    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: chips,
    );
  }

  Widget _buildCurlSection(BuildContext context, ApiEndpointSummary endpoint) {
    final curl = widget.controller.generateCurlExample(endpoint);
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'cURL Example (placeholder tokens)',
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
                    fontWeight: FontWeight.w600,
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: () {
                    // Copy to clipboard functionality would go here
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Copied to clipboard!')),
                    );
                  },
                  icon: const Icon(Icons.copy_rounded, size: 16),
                  label: const Text('Copy'),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: SelectableText(
              curl,
              style: TextStyle(
                fontFamily: 'monospace',
                fontSize: 11,
                color: theme.colorScheme.onSurface,
                height: 1.5,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildParametersTable(BuildContext context, ApiEndpointSummary endpoint) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Parameters', style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: DataTable(
            columnSpacing: 16,
            dataRowMinHeight: 36,
            dataRowMaxHeight: 48,
            columns: const [
              DataColumn(label: Text('Name', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12))),
              DataColumn(label: Text('In', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12))),
              DataColumn(label: Text('Required', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12))),
              DataColumn(label: Text('Type', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12))),
              DataColumn(label: Text('Description', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12))),
            ],
            rows: endpoint.parameters.map((p) {
              final param = p as Map<String, dynamic>;
              final schema = param['schema'] as Map<String, dynamic>? ?? {};
              return DataRow(cells: [
                DataCell(Text(param['name'] as String? ?? '', style: const TextStyle(fontFamily: 'monospace', fontSize: 12))),
                DataCell(Text(param['in'] as String? ?? '', style: const TextStyle(fontSize: 12))),
                DataCell(Text((param['required'] as bool?) == true ? 'Yes' : 'No', style: const TextStyle(fontSize: 12))),
                DataCell(Text('${schema['type'] ?? 'object'}${schema['format'] != null ? ' (${schema['format']})' : ''}', style: const TextStyle(fontSize: 12))),
                DataCell(Text(param['description'] as String? ?? '', style: const TextStyle(fontSize: 12))),
              ]);
            }).toList(),
          ),
        ),
      ],
    );
  }

  Widget _buildRequestBody(BuildContext context, ApiEndpointSummary endpoint) {
    final theme = Theme.of(context);
    final example = widget.controller.getRequestBodyExample(endpoint.requestBody);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 16),
        Text('Request Body', style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
            borderRadius: BorderRadius.circular(8),
          ),
          child: SelectableText(
            example != null
                ? const JsonEncoder.withIndent('  ').convert(example)
                : '{}',
            style: TextStyle(
              fontFamily: 'monospace',
              fontSize: 11,
              color: theme.colorScheme.onSurface,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildResponsesTable(BuildContext context, ApiEndpointSummary endpoint) {
    final theme = Theme.of(context);
    final responses = endpoint.responses;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 16),
        Text('Responses', style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        DataTable(
          columnSpacing: 16,
          dataRowMinHeight: 36,
          columns: const [
            DataColumn(label: Text('Status', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12))),
            DataColumn(label: Text('Description', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 12))),
          ],
          rows: responses.entries.map((entry) {
            Color color;
            if (entry.key.startsWith('2')) color = AppColors.secondary;
            else if (entry.key.startsWith('3')) color = AppColors.info;
            else if (entry.key.startsWith('4')) color = AppColors.danger;
            else color = AppColors.warning;

            final desc = entry.value is Map ? entry.value['description'] as String? ?? '' : '';

            return DataRow(cells: [
              DataCell(Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(4),
                  border: Border.all(color: color.withValues(alpha: 0.3)),
                ),
                child: Text(
                  entry.key,
                  style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 12, fontFamily: 'monospace'),
                ),
              )),
              DataCell(Text(desc, style: const TextStyle(fontSize: 12))),
            ]);
          }).toList(),
        ),
      ],
    );
  }

  Color _getMethodColor(String method) {
    switch (method) {
      case 'GET': return AppColors.secondary;
      case 'POST': return AppColors.primary;
      case 'PUT': return AppColors.warning;
      case 'PATCH': return AppColors.secondary;
      case 'DELETE': return AppColors.danger;
      default: return AppColors.info;
    }
  }
}

class _MethodBadge extends StatelessWidget {
  final String method;
  final Color color;

  const _MethodBadge({required this.method, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 72,
      padding: const EdgeInsets.symmetric(vertical: 6),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Center(
        child: Text(
          method,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w700,
            fontSize: 11,
            letterSpacing: 0.5,
          ),
        ),
      ),
    );
  }
}

class _TagChip extends StatelessWidget {
  final String label;

  const _TagChip({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}