# Backend — Internship Application Monitor API

Laravel 13 REST API for **Project 10: Internship Application Monitor** (PFEBSIT 002).

The full setup guide, demo accounts, API reference, state machine, and verification matrix
live in the [repository README](../README.md).

## Quick start

```powershell
composer install
copy .env.example .env
php artisan key:generate
```

Create the database and seed it:

```sql
CREATE DATABASE internship_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```powershell
php artisan migrate:fresh --seed
php artisan serve
```

## Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `DB_DATABASE` | `internship_monitor` | MySQL/MariaDB schema |
| `DB_USERNAME` | `root` | Local development user |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:*,http://127.0.0.1:*` | Comma-separated origins; `*` is supported for local Flutter web |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:*,127.0.0.1:*` | Token-auth only; no cookie/CSRF flow is used |

Tokens are issued by `POST /api/v1/auth/login` and revoked by `POST /api/v1/auth/logout`.

## Tests

```powershell
php artisan test
```

`tests/Feature/InternshipApplicationWorkflowTest.php` covers authentication, RBAC scoping,
validation envelopes, invalid state transitions, conditional transition payloads, and the
complete normal path through placement acknowledgement.