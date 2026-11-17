# Frontend — Internship Application Monitor (Flutter Web)

Material 3 Flutter Web client for **Project 10: Internship Application Monitor** (PFEBSIT 002).

The full setup guide, demo accounts, UI-state matrix, and verification steps live in the
[repository README](../README.md).

## Quick start

```powershell
flutter pub get
flutter run -d chrome
```

Point the client at a non-default backend port:

```powershell
flutter run -d chrome --dart-define=API_BASE_URL=http://127.0.0.1:8010/api/v1
```

## Configuration

`API_BASE_URL` is read once at compile time in `lib/core/config/app_config.dart`:

```dart
static const String apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://127.0.0.1:8000/api/v1',
);
```

No secrets, tokens, or credentials are embedded in the client. The bearer token is held in
memory by `ApiClient` for the lifetime of the session only.

## Checks

```powershell
flutter analyze
flutter test
flutter build web --release
```

## Layout

* `core/config` — environment-driven API base URL.
* `core/network` — HTTP wrapper, response envelope, typed exception mapping
  (`401`, `403`, `404`, `422`, `429`, `5xx`, timeout, socket failure).
* `core/theme` — dark-slate cyber-industrial palette with semantic status accents.
* `core/widgets` — `AsyncStateView`, `StatePanel`, status badge, responsive `AppShell`.
* `features/auth` — sign-in with controlled demo quick-fill.
* `features/applications` — dashboard, list, create/edit form, detail + audit timeline,
  role-gated action dialog, workflow guide.
* `state` — `ChangeNotifier` controllers for auth, list, detail, and form state.