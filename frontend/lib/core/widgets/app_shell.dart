import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:provider/single_child_widget.dart';

import '../../features/applications/application_form_screen.dart';
import '../../features/applications/dashboard_screen.dart';
import '../../features/applications/workflow_guide_screen.dart';
import '../../features/automation/automation_monitor_screen.dart';
import '../../models/user_model.dart';
import '../../services/application_service.dart';
import '../../services/automation_service.dart';
import '../../state/application_list_controller.dart';
import '../../state/auth_controller.dart';
import '../../state/automation_monitor_controller.dart';
import '../config/app_config.dart';
import '../theme/app_theme.dart';

class AppShell extends StatelessWidget {
  const AppShell({super.key});

  @override
  Widget build(BuildContext context) {
    final UserModel? user = context.watch<AuthController>().user;

    if (user == null) {
      return const SizedBox.shrink();
    }

    return MultiProvider(
      providers: <SingleChildWidget>[
        ChangeNotifierProvider<ApplicationListController>(
          create: (_) => ApplicationListController(
            service: context.read<ApplicationService>(),
          )..load(),
        ),
        if (user.isCoordinator)
          ChangeNotifierProvider<AutomationMonitorController>(
            create: (_) => AutomationMonitorController(
              service: context.read<AutomationService>(),
              canManage: true,
            )..load(),
          ),
      ],
      child: _AppShellView(user: user),
    );
  }
}

class _Destination {
  const _Destination({
    required this.icon,
    required this.label,
    required this.builder,
  });

  final IconData icon;
  final String label;
  final Widget Function() builder;
}

class _AppShellView extends StatefulWidget {
  const _AppShellView({required this.user});

  final UserModel user;

  @override
  State<_AppShellView> createState() => _AppShellViewState();
}

class _AppShellViewState extends State<_AppShellView> {
  int _index = 0;

  List<_Destination> _destinationsFor(UserModel user) {
    final List<_Destination> destinations = <_Destination>[
      const _Destination(
        icon: Icons.dashboard_rounded,
        label: 'Dashboard',
        builder: DashboardScreen.new,
      ),
    ];

    if (user.isStudent) {
      destinations.add(
        _Destination(
          icon: Icons.add_box_rounded,
          label: 'New Application',
          builder: () => const ApplicationFormScreen(),
        ),
      );
    }

    if (user.isCoordinator) {
      destinations.add(
        const _Destination(
          icon: Icons.auto_mode_rounded,
          label: 'Automation',
          builder: AutomationMonitorScreen.new,
        ),
      );
    }

    destinations.add(
      const _Destination(
        icon: Icons.account_tree_rounded,
        label: 'Workflow Guide',
        builder: WorkflowGuideScreen.new,
      ),
    );

    return destinations;
  }

  void _handleSelected(int index) {
    setState(() => _index = index);
  }

  Future<void> _confirmLogout() async {
    final AuthController auth = context.read<AuthController>();

    final bool? shouldLogout = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: const Text('Sign out?'),
          content: const Text(
            'You will need to sign in again to continue monitoring internship applications.',
          ),
          actions: <Widget>[
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(true),
              child: const Text('Sign out'),
            ),
          ],
        );
      },
    );

    if (shouldLogout == true) {
      await auth.logout();
    }
  }

  @override
  Widget build(BuildContext context) {
    final List<_Destination> destinations = _destinationsFor(widget.user);
    final double width = MediaQuery.sizeOf(context).width;
    final bool useRail = width >= AppConfig.wideBreakpoint;
    final int safeIndex = _index.clamp(0, destinations.length - 1);
    final _Destination current = destinations[safeIndex];

    final Widget content = IndexedStack(
      index: safeIndex,
      children: destinations
          .map((_Destination destination) => destination.builder())
          .toList(growable: false),
    );

    if (useRail) {
      return Scaffold(
        body: Row(
          children: <Widget>[
            SafeArea(
              child: NavigationRail(
                selectedIndex: safeIndex,
                onDestinationSelected: _handleSelected,
                labelType: NavigationRailLabelType.all,
                leading: const _BrandMark(expanded: false),
                destinations: destinations
                    .map(
                      (_Destination destination) => NavigationRailDestination(
                        icon: Icon(destination.icon),
                        label: Text(destination.label),
                      ),
                    )
                    .toList(growable: false),
                trailing: SizedBox(
                  width: 216,
                  child: _UserPanel(
                    user: widget.user,
                    onLogout: _confirmLogout,
                  ),
                ),
              ),
            ),
            const VerticalDivider(width: 1),
            Expanded(child: content),
          ],
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(current.label, style: Theme.of(context).textTheme.titleLarge),
            Text(
              'Internship Application Monitor',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
      drawer: Drawer(
        child: SafeArea(
          child: Column(
            children: <Widget>[
              const _BrandMark(expanded: true),
              const Divider(),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: destinations
                      .map(
                        (_Destination destination) => ListTile(
                          leading: Icon(destination.icon),
                          title: Text(destination.label),
                          selected: destinations[safeIndex] == destination,
                          onTap: () {
                            Navigator.of(context).pop();
                            _handleSelected(destinations.indexOf(destination));
                          },
                        ),
                      )
                      .toList(growable: false),
                ),
              ),
              const Divider(),
              _UserPanel(
                user: widget.user,
                onLogout: () async {
                  Navigator.of(context).pop();
                  await _confirmLogout();
                },
              ),
            ],
          ),
        ),
      ),
      body: content,
    );
  }
}

class _BrandMark extends StatelessWidget {
  const _BrandMark({required this.expanded});

  final bool expanded;

  @override
  Widget build(BuildContext context) {
    final Widget badge = Container(
      width: 46,
      height: 46,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: <Color>[AppColors.primary, AppColors.secondary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(14),
      ),
      child: const Icon(Icons.work_outline_rounded, color: AppColors.onPrimary),
    );

    if (!expanded) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 20),
        child: badge,
      );
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 24, 16, 20),
      child: Row(
        children: <Widget>[
          badge,
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Text(
                  'Internship Monitor',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                Text(
                  'PFEBSIT 002 · Project 10',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _UserPanel extends StatelessWidget {
  const _UserPanel({required this.user, required this.onLogout});

  final UserModel user;
  final Future<void> Function() onLogout;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const Divider(),
          const SizedBox(height: 8),
          Row(
            children: <Widget>[
              CircleAvatar(
                radius: 18,
                backgroundColor: AppColors.primary.withValues(alpha: 0.18),
                child: Text(
                  user.initials,
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Text(
                      user.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    Text(
                      user.roleLabel,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.secondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: onLogout,
            icon: const Icon(Icons.logout_rounded, size: 18),
            label: const Text('Sign out'),
          ),
        ],
      ),
    );
  }
}