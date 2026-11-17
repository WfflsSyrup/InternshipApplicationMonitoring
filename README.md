# Project 10 — Internship Application Monitor

**PFEBSIT 002 — Integrative Programming and Technologies 2**
University of La Salette, Inc. · College of Information Technology

A full-stack monitoring system for internship applications across four roles:
**student**, **adviser**, **internship coordinator**, and **partner reviewer**.

| Part | Stack | Location |
| --- | --- | --- |
| REST API | Laravel 13 · PHP 8.2+ · MySQL/MariaDB · Sanctum | `backend/` |
| Web client | Flutter 3.22+ · Dart · Material 3 | `frontend/` |

---

## How to run the project

> Tested on Windows 11 / PowerShell with XAMPP (MariaDB 10.11), PHP 8.5, Composer 2.10 and
> Flutter 3.47. Steps below are Windows PowerShell; macOS/Linux users can run the same
> commands without the `copy` alias (use `cp`).

### Step 0 — Start the prerequisites

1. **Start MySQL** (XAMPP Control Panel → **Start** on the *MySQL* row, or run
   `C:\xampp\mysql_start.bat`). Confirm it is up:

   ```powershell
   & "C:\xampp\mysql\bin\mysql.exe" -u root -e "SELECT VERSION();"
   ```

2. **Verify the toolchain** (all four must print a version):

   ```powershell
   php -v; composer -V; flutter --version; dart --version
   ```

3. **Create the database once:**

   ```powershell
   & "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS internship_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

   If your MySQL `root` user has a password, open `backend\.env` and set `DB_PASSWORD`.

---

### Step 1 — Run the backend (Terminal 1)

```powershell
cd "C:\Users\Jeremy Jay\flutter_projects\InternshipMonitoring\backend"

composer install
copy .env.example .env          # skip this line if .env already exists
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Expected output:

```
INFO  Server running on [http://127.0.0.1:8000].
```

Verify it in a browser or with:

```powershell
curl.exe http://127.0.0.1:8000/api/v1/health
```

```json
{"success":true,"message":"Service is healthy.","data":{"status":"ok","database":"connected", ...},"errors":null}
```

> **Port 8000 already in use?** Another local project may be serving there. Run
> `php artisan serve --port=8010` instead, then use the `--dart-define` flag in Step 2.

---

### Step 2 — Run the frontend (Terminal 2)

```powershell
cd "C:\Users\Jeremy Jay\flutter_projects\InternshipMonitoring\frontend"

flutter pub get
flutter run -d chrome
```

Chrome opens at `http://localhost:<random-port>`. If you changed the API port:

```powershell
flutter run -d chrome --dart-define=API_BASE_URL=http://127.0.0.1:8010/api/v1
```

---

### Step 3 — Sign in with a demo account

Type the credentials for the role you want to test. The sign-in form has no one-tap role
shortcuts — every login is a normal email + password submission.

| Role | Email | Password |
| --- | --- | --- |
| Student | `student1.demo@uls-test.edu.ph` | `Password123!` |
| Adviser | `adviser.demo@uls-test.edu.ph` | `Password123!` |
| Coordinator | `coord.demo@uls-test.edu.ph` | `Password123!` |
| Partner Reviewer | `partner.demo@techcorp-test.com` | `Password123!` |

All four demo accounts share the password `Password123!`. All data is fictional.

---

### Step 4 — Walk the normal path (about 2 minutes)

| # | Sign in as | Do this | Expected |
| --- | --- | --- | --- |
| 1 | Student | **New Application** → fill the form → **Submit for review** | Status `Draft` → `Submitted` |
| 2 | Adviser | Open the record → **Verify Requirements** → **Endorse Application** | `Requirements Verified` → `Endorsed` |
| 3 | Coordinator | Open the record → **Submit To Partner** → pick a reviewer | `Submitted to Partner` |
| 4 | Partner Reviewer | **Schedule Interview** → date + time → **Record Passed Interview** | `Interview Scheduled` → `Interview Passed` |
| 5 | Coordinator | **Confirm Placement** | `Placement Confirmed` |
| 6 | Student | **Acknowledge Placement** | Acknowledgement timestamp recorded |
| 7 | Anyone | Open the record | Audit timeline with every transition, actor and remarks |

Sign out with the **Sign out** button in the navigation rail (desktop) or drawer (narrow).

---

### Step 5 — Automation monitor (about 3 minutes)

Sign in as the **coordinator** and open **Automation** in the navigation rail (desktop) or drawer
(narrow). This is Module 13 / SA7: scheduled sweeps, bounded retries, sanitised error summaries and
coordinator recovery. Start from an empty job table (a fresh `--seed` run, or
`php artisan tinker --execute="App\Models\AutomationJob::query()->delete();"`) so the walkthrough is
reproducible — a repeated sweep skips duplicates instead of creating new jobs.

| # | Do this | Expected |
| --- | --- | --- |
| 1 | Open **Automation** | "No automation jobs recorded yet" empty state with a **Run a sweep** CTA |
| 2 | **Run Controlled Sweep** → *Stalled escalations* → fault mode *Permanent invalid recipient* → **Run Sweep** | Summary Snack bar; 2 jobs appear as `Manual Review Required` |
| 3 | Inspect a job card | Status badge, `correlation_id` prefix, `1/3` attempts, `HTTP 422` error, "Coordinator action required" callout |
| 4 | **Requeue for Recovery** → add a remark → **Requeue** | Job flips to `Succeeded`; the remark stays visible on the card |
| 5 | **Run Controlled Sweep** → *Weekly summary* → fault mode *None* → **Run Sweep** | 1 succeeded *Weekly Placement Summary* job + weekly summary card at the top |
| 6 | Open the seeded record `INT-2026-0003` | **Linked automation jobs** card lists only that application's jobs |

Fault injection is rejected with `422` unless `AUTOMATION_ALLOW_FAULT_INJECTION=true` in
`backend\.env`, which keeps production safe while allowing the retry policy to be demonstrated. A
*Transient failure once* run shows the job in `Retrying` with a `next_retry_at`, and the next sweep
delivers it.

Schedules (all in `Asia/Manila`, `withoutOverlapping`, idempotent per day / ISO week):

| Command | Schedule | Purpose |
| --- | --- | --- |
| `internship:sweep-incomplete` | daily `08:00` | Draft / revision-requested records missing a requirement for 48h |
| `internship:sweep-stalled` | daily `08:30` | Applications stuck ≥ 5 days in submitted, requirements verified or partner submitted |
| `internship:weekly-summary` | Friday `17:00` | ISO-week pipeline counts to the coordinator |

Run any of them manually:

```powershell
cd "C:\Users\Jeremy Jay\flutter_projects\InternshipMonitoring\backend"
php artisan internship:sweep-incomplete
php artisan internship:sweep-stalled
php artisan internship:weekly-summary
Get-Content storage\logs\automation.log -Tail 3
```

---

### Step 6 — Cryptographic payload protection (about 3 minutes)

Module 14 / SA8 covers the sealed channel between the internship office and the industry partner:
AES-256-GCM envelopes with a fresh 12-byte IV, AAD binding `{reference_code}|{key_id}|{direction}`,
detached HMAC-SHA256 request signing, an atomic nonce replay store and a coordinator-only verification
lab that proves each rejection path on purpose.

Sign in as the **coordinator** and open **Crypto** in the navigation rail (desktop) or drawer (narrow).

| # | Do this | Expected |
| --- | --- | --- |
| 1 | Open **Crypto** | Keyring & policy banner (active key `v2`, replay window `±300s`, grace keys), counters and the audit list |
| 2 | **Seal & Dispatch** → pick `INT-2026-0003` (partner submitted) → **Seal & Dispatch** | Snack bar with the new envelope; card shows `Verified`, `Outbound partner packet`, key `v2`, `sha256:` prefix |
| 3 | **Verification Lab** → *Valid callback* → **Run Verification** | Result card: `Accepted · HTTP 200`, all steps `verified` |
| 4 | **Verification Lab** → *Tampered ciphertext* | `Rejected · rejected_tamper · HTTP 422`, the AES-256-GCM tag catches the flip |
| 5 | **Verification Lab** → *Forged signature* | `Rejected · rejected_signature · HTTP 401` |
| 6 | **Verification Lab** → *Replay attack* (twice) | First `Accepted`, second `Rejected · rejected_replay · HTTP 409` from the nonce store |
| 7 | **Verification Lab** → *Expired timestamp* | `Rejected · rejected_expired · HTTP 401` from the ±300s skew check |

The lab is gated by `CRYPTO_ALLOW_TAMPER_SIMULATION=true` in `backend\.env`; production must keep it
`false`, which makes `POST /integrations/verify-simulation` return `503`. The keyring endpoint never
returns key material — it reports the active key id, supported grace keys and envelope totals only.

Key material lives in `backend\.env` as base64 256-bit values and is **never** shipped to the client:

```powershell
cd "C:\Users\Jeremy Jay\flutter_projects\InternshipMonitoring\backend"
[Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Maximum 256 }))
php artisan crypto:rotate-envelopes --dry-run   # plan a v2 -> v3 rotation
php artisan crypto:rotate-envelopes             # re-seal every active envelope
php artisan integration:prune-nonces            # drop expired nonce rows (daily 02:15)
```

The public partner webhook is `POST /api/v1/integrations/partner-callbacks/outcome`; it accepts the
`X-Key-Id`, `X-Timestamp`, `X-Nonce` and `X-Signature-256` headers and rejects anything that fails the
signature, skew, replay or AAD checks. Valid callbacks transition the application through the normal
workflow service, so the crypto layer never writes state directly.

---

### Step 7 — Reset the demo data (optional)

`migrate:fresh` **deletes all data** and reseeds the controlled demo set:

```powershell
cd "C:\Users\Jeremy Jay\flutter_projects\InternshipMonitoring\backend"
php artisan migrate:fresh --seed
```

Seeded records:

| Reference | Student | Host company | Status |
| --- | --- | --- | --- |
| `INT-2026-0001` | student1 | TechCorp Test Solutions | `submitted` |
| `INT-2026-0002` | student2 | DataNest Analytics | `requirements_verified` |
| `INT-2026-0003` | student1 | TechCorp Test Solutions | `partner_submitted` |
| `INT-2026-0004` | student2 | Nexa Systems Inc. | `interview_scheduled` |

---

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| Client shows **"Cannot reach the backend API"** | Backend not running, or wrong port | Start `php artisan serve`; match `API_BASE_URL` |
| Client shows **"Service temporarily unavailable"** | MySQL stopped, or DB credentials wrong | Start MySQL; check `DB_*` in `backend\.env` |
| `php artisan migrate` → `Access denied for user 'root'` | MySQL root has a password | Set `DB_PASSWORD` in `backend\.env` |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL is not running | Start MySQL from XAMPP first |
| Login always returns `422` | Demo password is case-sensitive | Use exactly `Password123!` |
| Blank page on `flutter run -d chrome` | Chrome blocked the debug service | `flutter clean` then `flutter run -d chrome` again |
| Port 8000 busy | Another local API already listening | `php artisan serve --port=8010` + `--dart-define` |
| `Cannot hit test a render box that has never been laid out` on **Sign out** | `NavigationRail.trailing` hands its child unbounded width, so the button inside it never lays out | Keep a bounded width on the rail `trailing` panel (fixed in `app_shell.dart`) |
| Yellow-black overflow stripes in a narrow window | Header/actions were a single `Row`, and state panels had no scroll fallback | Header uses `Wrap`; `StatePanel` scrolls when space is short |
| Sweep returns `422` "fault injection is disabled" | `AUTOMATION_ALLOW_FAULT_INJECTION` is `false` | Set it to `true` in `backend\.env`, then restart `php artisan serve` |
| Sweep reports `duplicates_skipped` instead of `enqueued` | The job for that day / ISO week already exists | Expected: sweeps are idempotent. Clear `automation_jobs` to force a fresh run |
| **Seal & Dispatch** returns `503` | No `CRYPTO_KEY_*` values are set | Generate base64 256-bit keys and fill `CRYPTO_KEY_V2_ENC` / `CRYPTO_KEY_V2_SIG` in `backend\.env` |
| **Verification Lab** returns `503` | `CRYPTO_ALLOW_TAMPER_SIMULATION` is `false` | Set it to `true` in `backend\.env`, restart `php artisan serve` |
| Partner callback returns `409 rejected_replay` | The nonce was already delivered | Expected: replay protection. Re-issue the request with a fresh nonce and timestamp |
| Partner callback returns `401 rejected_expired` | Client clock differs from the server by more than `CRYPTO_CLOCK_SKEW_SECONDS` | Sync the clock; the window is ±300s by default |

---

## Configuration

### Backend (`backend/.env`)

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_URL` | `http://localhost:8000` | Base URL of the API |
| `DB_CONNECTION` | `mysql` | Database driver |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | MySQL host and port |
| `DB_DATABASE` | `internship_monitor` | Schema created in Step 0 |
| `DB_USERNAME` / `DB_PASSWORD` | `root` / *(empty)* | MySQL credentials |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:*,http://127.0.0.1:*` | Comma-separated Flutter web origins |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:*,127.0.0.1:*` | Token-auth domains (no cookie flow) |
| `AUTOMATION_DISPATCHER_MODE` | `log` | `log` (structured file only), `http` (POST to `AUTOMATION_DISPATCHER_URL`) or `disabled` |
| `AUTOMATION_DISPATCHER_URL` / `AUTOMATION_DISPATCHER_TOKEN` | *(empty)* | Target + bearer token for `http` dispatch mode |
| `AUTOMATION_MAX_ATTEMPTS` | `3` | Bounded retry ceiling per job |
| `AUTOMATION_RETRY_BASE_SECONDS` / `AUTOMATION_RETRY_JITTER_SECONDS` | `10` / `3` | Exponential backoff base and jitter |
| `AUTOMATION_TIMEOUT_SECONDS` | `5` | HTTP dispatcher timeout |
| `AUTOMATION_ALLOW_FAULT_INJECTION` | `false` | Enables the test-only fault modes; keep `false` in production |
| `AUTOMATION_FALLBACK_RECIPIENT` | `coord.demo@uls-test.edu.ph` | Recipient when resolution fails |
| `AUTOMATION_INCOMPLETE_AFTER_HOURS` / `AUTOMATION_STALLED_AFTER_DAYS` | `48` / `5` | Sweep thresholds |
| `AUTOMATION_SWEEP_DISPATCH_LIMIT` | `50` | Max pending jobs dispatched per run |
| `CRYPTO_ACTIVE_KID` | `v2` | Key id used for new seals and for AAD binding |
| `CRYPTO_KEY_V1_ENC` / `CRYPTO_KEY_V1_SIG` | *(empty in `.env.example`)* | Base64 256-bit AES + HMAC key for grace id `v1` |
| `CRYPTO_KEY_V2_ENC` / `CRYPTO_KEY_V2_SIG` | *(empty in `.env.example`)* | Base64 256-bit AES + HMAC key for the active id |
| `CRYPTO_CLOCK_SKEW_SECONDS` | `300` | Accepted timestamp window; outside → `401 rejected_expired` |
| `CRYPTO_NONCE_TTL_SECONDS` | `900` | Retention for `used_integration_nonces` rows |
| `CRYPTO_ALLOW_TAMPER_SIMULATION` | `false` | Enables the coordinator verification lab; keep `false` in production |

### Frontend (`frontend`)

`API_BASE_URL` is read at compile time in `lib/core/config/app_config.dart`:

```dart
static const String apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://127.0.0.1:8000/api/v1',
);
```

No secrets, tokens or credentials are embedded in the client. The bearer token lives in
memory for the session only.

---

## Verification

### Automated tests

```powershell
# backend — 47 feature tests (workflow, RBAC, validation, automation, crypto, error envelopes)
cd backend
php artisan test

# frontend — static analysis, widget tests, release build
cd frontend
flutter analyze
flutter test          # login, shell layouts 1280px/420px, automation monitor, crypto integration
flutter build web --release
```

All three pass on the current codebase: **47 backend tests / 381 assertions** and **11 Flutter widget
tests**. The project is created with `--platforms=web` only, so `flutter build apk` is not
available — Flutter Web is the deliverable target.

### Adverse-path checklist

| Case | How to trigger | Expected |
| --- | --- | --- |
| `422` validation | Submit the application form empty, or set hours to `0` | `errors` map keyed by field |
| `422` invalid jump | Student tries `Draft → Endorsed` | `errors.status` explains the rule |
| `401` | Call a protected endpoint without a token | `Unauthenticated.` + session-expired notice |
| `403` | Student opens another student's record; non-coordinator calls `/partner-reviewers` | "Access Denied" panel, no crash |
| Network offline | Stop `php artisan serve`, press **Retry Connection** | "Cannot reach the backend API" card |
| `503` | Stop MySQL, then `GET /api/v1/health` | `503` + "Service temporarily unavailable" |
| `401 rejected_signature` | Verification lab → *Forged signature* | "Rejected · rejected_signature · HTTP 401", envelope row with the rejection reason |
| `422 rejected_tamper` | Verification lab → *Tampered ciphertext* | "Rejected · rejected_tamper · HTTP 422" from the GCM tag check |
| `409 rejected_replay` | Verification lab → *Replay attack* twice | Second attempt "Rejected · rejected_replay · HTTP 409" from the nonce store |
| `401 rejected_expired` | Verification lab → *Expired timestamp* | "Rejected · rejected_expired · HTTP 401" from the ±300s skew check |

---

## API reference

Every response uses one envelope:

```json
{ "success": true, "message": "…", "data": {}, "errors": null }
```

| Method | Endpoint | Access | Purpose |
| --- | --- | --- | --- |
| GET | `/api/v1/health` | public | Service + database health |
| POST | `/api/v1/auth/login` | public | Sanctum token login (throttled 6/min) |
| POST | `/api/v1/auth/logout` | token | Revoke the current token |
| GET | `/api/v1/auth/me` | token | Current user profile |
| GET | `/api/v1/applications` | token | Role-scoped listing (`?status=`) |
| POST | `/api/v1/applications` | student | Create draft + audit entry |
| GET | `/api/v1/applications/{id}` | policy | Application + full audit trail |
| PUT | `/api/v1/applications/{id}` | student, `draft`/`revision_requested` | Update details |
| POST | `/api/v1/applications/{id}/transition` | role + state machine | Workflow transition |
| GET | `/api/v1/partner-reviewers` | coordinator | Directory for the routing dialog |
| GET | `/api/v1/automation/jobs` | token, role-scoped | Automation jobs (`?status=`, `?application_id=`) |
| GET | `/api/v1/automation/summaries/weekly` | token, role-scoped | Latest succeeded weekly summary payload |
| POST | `/api/v1/automation/sweeps/run` | coordinator | Run a controlled sweep (`type`, optional `fault_mode`) |
| POST | `/api/v1/automation/jobs/{job}/requeue` | coordinator | Recovery requeue (`recipient_identifier`, `remarks`) |
| GET | `/api/v1/integrations/envelopes` | token, role-scoped | Crypto envelope metadata (`?status=`, `?direction=`, `?application_id=`) |
| GET | `/api/v1/integrations/keyring/status` | coordinator | Active key id, grace keys, policy — no key material |
| POST | `/api/v1/integrations/partner-packets/dispatch` | coordinator | Seal and persist an outbound partner packet |
| POST | `/api/v1/integrations/partner-callbacks/outcome` | public (signed) | Inbound partner webhook (60/min) |
| POST | `/api/v1/integrations/verify-simulation` | coordinator | Controlled rejection-path lab (`503` when disabled) |

Listing scope: coordinators see everything, students see their own, advisers see assigned
applications plus same-department students, partner reviewers see their assigned applications
from `partner_submitted` onward.

### State machine

| From | To | Allowed roles |
| --- | --- | --- |
| `draft` | `submitted` | student |
| `submitted` | `requirements_verified`, `revision_requested` | adviser, coordinator |
| `revision_requested` | `submitted` | student |
| `requirements_verified` | `endorsed` | adviser, coordinator |
| `endorsed` | `partner_submitted` | coordinator |
| `partner_submitted` | `interview_scheduled` | partner_reviewer, coordinator |
| `interview_scheduled` | `interview_passed`, `interview_failed` | partner_reviewer |
| `interview_passed` | `placement_confirmed` | coordinator |
| `placement_confirmed` | `placement_confirmed` (acknowledgement) | student |
| any active state | `withdrawn` | student |
| any active state | `rejected` | coordinator, partner_reviewer |

Transition payload:

```json
{
  "target_status": "partner_submitted",
  "remarks": "Endorsed package forwarded to the partner reviewer.",
  "partner_reviewer_id": 3,
  "interview_schedule": "2026-12-01 09:00:00",
  "interview_notes": "Panel interview with the engineering team."
}
```

Conditional rules (enforced in the FormRequest and re-checked inside the service transaction):

* `partner_submitted` requires a valid `partner_reviewer_id`.
* `interview_scheduled` requires a future `interview_schedule`.
* `revision_requested` and `rejected` require `remarks`.
* The coordinator confirms a placement (stamps `placement_confirmed_at`); the student then
  acknowledges it (stamps `placement_acknowledged_at`), keeping the status enum unchanged
  while making the acknowledgement auditable.

### Automation jobs (Week 13 / Module 13 · SA7)

Every sweep, milestone notification and summary is a persisted `automation_jobs` row with its own
`correlation_id`, `idempotency_key` and attempt counter. Nothing is fire-and-forget.

| Status | Meaning |
| --- | --- |
| `queued` | Created, waiting for the first dispatch |
| `retrying` | Failed transiently, waiting for the next bounded retry |
| `succeeded` | Delivered and stamped with `completed_at` |
| `manual_review_required` | Permanent failure or retries exhausted — coordinator must requeue |

Delivery policy (`backend\config\automation.php`, override with `AUTOMATION_*` env keys):

* `AUTOMATION_DISPATCHER_MODE` — `log` (default, structured `storage/logs/automation.log` only),
  `http` (`AUTOMATION_DISPATCHER_URL`) or `disabled`.
* `AUTOMATION_MAX_ATTEMPTS` (default `3`), `AUTOMATION_RETRY_BASE_SECONDS` (default `10`),
  `AUTOMATION_RETRY_JITTER_SECONDS` (default `3`) — exponential backoff plus jitter, recorded in
  `next_retry_at`.
* `AUTOMATION_ALLOW_FAULT_INJECTION` — must stay `false` in production; when `false` the API
  rejects a `fault_mode` with `422` instead of simulating a failure.
* `AUTOMATION_INCOMPLETE_AFTER_HOURS` (`48`), `AUTOMATION_STALLED_AFTER_DAYS` (`5`),
  `AUTOMATION_SWEEP_DISPATCH_LIMIT` (`50`).

Payload for `POST /api/v1/automation/sweeps/run`:

```json
{
  "type": "stalled",
  "fault_mode": "transient_once"
}
```

Response data (also the `data` of every sweep, and the `dispatch` block of a requeue):

```json
{
  "type": "stalled",
  "scanned": 4,
  "enqueued": 2,
  "duplicates_skipped": 1,
  "dispatch": {
    "processed": 2,
    "succeeded": 1,
    "retry_scheduled": 1,
    "manual_review_required": 0
  }
}
```

Error summaries are sanitised before storage: tokens, bearer headers and raw response bodies are
stripped, so a job card never leaks credentials. Requeue body:

```json
{
  "recipient_identifier": "EMP-ADV-001",
  "remarks": "Recipient corrected after the adviser account was re-provisioned."
}
```

---

### Cryptographic envelopes (Week 14 / Module 14 · SA8)

Sealing lives in `app/Services/Crypto/EnvelopeCryptoService.php` and is the only writer of
`secure_integration_envelopes` rows. Ciphertext, IV and tags are stored in the database but never
returned by the API — `GET /integrations/envelopes` returns metadata only.

| Aspect | Implementation |
| --- | --- |
| Cipher | AES-256-GCM, fresh 12-byte IV per seal |
| AAD | `{reference_code}\|{key_id}\|{direction}` — re-checked after decryption |
| Signature | HMAC-SHA256 over `{METHOD}\n{PATH}\n{TS}\n{NONCE}\n{KEY_ID}\n{SHA256_HEX(body)}` |
| Headers | `X-Key-Id`, `X-Timestamp`, `X-Nonce`, `X-Signature-256: sha256=<hex>` |
| Replay | `used_integration_nonces` UNIQUE insert; second delivery → `409 rejected_replay` |
| Skew | ±`CRYPTO_CLOCK_SKEW_SECONDS` (300s); outside → `401 rejected_expired` |
| Comparison | `hash_equals` for both signature and post-decrypt AAD |
| Keyring | `CRYPTO_KEY_V1_ENC/SIG` + `CRYPTO_KEY_V2_ENC/SIG`, active id `CRYPTO_ACTIVE_KID` |

| Envelope status | Meaning |
| --- | --- |
| `verified` | Signature, skew, nonce and GCM tag all passed |
| `rejected_signature` | `401` — HMAC mismatch (forged or unknown key) |
| `rejected_tamper` | `422` — GCM authentication tag failed |
| `rejected_replay` | `409` — nonce already consumed |
| `rejected_expired` | `401` — timestamp outside the skew window |

Rejection reasons are recorded on the envelope row with a `SIM-` prefixed `correlation_id` when
produced by the verification lab, so a demonstration never looks like a production callback. The lab
is coordinator-only and gated by `CRYPTO_ALLOW_TAMPER_SIMULATION`; outbound dispatch records
`replay_checked = false` because it never consumes a nonce.

---

## Repository layout

```text
InternshipMonitoring/
├── backend/      # Laravel REST API
│   ├── app/
│   │   ├── Console/Commands/{SweepIncompleteCommand, SweepStalledCommand, WeeklySummaryCommand,
│   │   │            CryptoRotateEnvelopesCommand, PruneIntegrationNoncesCommand}.php
│   │   ├── Http/{Controllers/Api/V1, Requests, Resources}/
│   │   ├── Models/{User, InternshipApplication, ApplicationStatusLog, AutomationJob,
│   │   │          SecureIntegrationEnvelope, UsedIntegrationNonce}.php
│   │   ├── Policies/{InternshipApplicationPolicy, AutomationJobPolicy,
│   │   │            SecureIntegrationEnvelopePolicy}.php
│   │   ├── Services/{ApplicationWorkflowService, Automation/{AutomationJobService,
│   │   │            AutomationDispatcherService, AutomationSweepService},
│   │   │            Crypto/EnvelopeCryptoService}.php
│   │   ├── Support/{ApiResponse, ApiExceptionRenderer, ApplicationStatusMachine,
│   │   │          Automation/{LogSanitizer, AutomationRecipientResolver},
│   │   │          Crypto/CryptoLogSanitizer}.php
│   │   └── Exceptions/{InvalidStateTransitionException, Crypto/{CryptoConfigurationException,
│   │              CryptoVerificationException}}.php
│   ├── config/{automation.php, crypto.php}
│   ├── database/{migrations,seeders,factories}/
│   ├── routes/{api.php, console.php}
│   └── tests/Feature/{InternshipApplicationWorkflowTest, Week13AutomationWorkflowTest,
│           Week14CryptographicIntegrationTest}.php
├── frontend/     # Flutter Web client
│   └── lib/
│       ├── core/{config, network, theme, utils, widgets}/
│       ├── features/{auth, applications, automation, crypto}/
│       ├── models/{automation_job_model, automation_sweep_result, crypto_envelope_model,
│       │           crypto_keyring_model, crypto_simulation_result}.dart
│       ├── services/{application_service, auth_service, automation_service, crypto_service}.dart
│       ├── state/{auth_controller, application_list_controller, application_detail_controller,
│       │          automation_monitor_controller, crypto_integration_controller}.dart
│       └── main.dart
└── README.md
```

---

## UI states covered

| State | Implementation |
| --- | --- |
| Loading | `LoadingStateView` via `AsyncStateView` |
| Success | Snack bar + refreshed data after every mutation |
| Empty | Actionable empty state with a student "Create application" CTA |
| Validation | Client-side checks, then server `422` errors mapped onto the exact fields |
| Authentication (`401`) | Session-expired notice, returns to sign-in |
| Authorization (`403`) | "Access Denied" panel, no crash |
| Network error | "Cannot reach the backend API" + **Retry Connection** |
| Server error (`500`/`503`) | Generic "Service temporarily unavailable", no stack traces |

Layout is responsive: `NavigationRail` at `≥ 768px`, collapsible `Drawer` below that, with
high-contrast status badges and keyboard-focusable controls throughout.

The **Automation** screen (coordinator only) adds its own states: queued / retrying / succeeded /
manual-review-required job badges, an actionable "no jobs yet" empty state with a sweep CTA, an
inline "linked automation jobs could not be loaded" retry card on the application detail page, and a
"fault injection is disabled in this environment" `422` notice surfaced through the Snack bar.

The **Crypto** screen (open to every role, actions coordinator-only) adds its own states: verified /
rejected-signature / rejected-tamper / rejected-replay / rejected-expired badges, a keyring policy
banner with a "verification lab disabled" warning when `CRYPTO_ALLOW_TAMPER_SIMULATION=false`, a
"key material exposure" danger note, a read-only notice for non-coordinators, and an inline
"cryptographic envelopes could not be loaded" retry card on the application detail page.

---

## Deliberate scope boundary

**Implemented now:** Sanctum token authentication, policy-based RBAC, the complete bounded
lifecycle with an append-only audit trail, standardized JSON envelopes with safe exception
rendering, a controlled anonymized seeder, scheduled automation sweeps with bounded retries and
coordinator recovery (Week 13 / Module 13 · SA7), AES-256-GCM payload protection with detached
HMAC-SHA256 request signing and replay protection (Week 14 / Module 14 · SA8), and the responsive
Flutter Web client with all eight mandatory UI states.

**Intentionally deferred (Week 15–18):** an external queue daemon in front of the DB-backed job
table, k6 / JMeter load scripts, OpenAPI YAML generation, and UAT report templates.

Each can be added without refactoring: the dispatcher already writes to a structured log or an HTTP
endpoint selected by `AUTOMATION_DISPATCHER_MODE`, payload wrapping fits between `ApiClient` and
`ApiExceptionRenderer`, and `ApplicationWorkflowService` already exposes a single transition table
that any scheduler or test harness can reuse.