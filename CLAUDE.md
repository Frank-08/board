# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

"Together in Council" is a LAMP-stack (Linux/Apache/MySQL/PHP) governance board meeting management system: meeting types, board members, meetings, agendas, minutes, resolutions, procedural proposals, and document links. No frontend framework, no build step, no test suite, no Composer/npm dependency manifest — plain PHP pages with vanilla JS calling a JSON API, server-rendered with `<?php ... ?>` inline in HTML.

## Running locally

There is no dev server, build, lint, or test command in this repo — it runs directly under Apache + PHP-FPM/mod_php.

1. Point Apache's document root (or a vhost) at the repo root; `.htaccess` handles rewrites (`AllowOverride All`, `mod_rewrite` required).
2. Create `config/database.php` (or copy the `.local` pattern) with `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS`; `getDBConnection()` there returns a PDO connection (exceptions on, `FETCH_ASSOC` default, emulated prepares off).
3. Import `database/schema.sql` for a fresh DB, then apply any needed `database/migration_*.sql` files (schema.sql is NOT auto-kept-in-sync with new migrations — check the migration filenames/dates against schema.sql when in doubt about current shape).
4. `config/config.php` holds app-wide constants (`APP_NAME`, `BASE_URL`, logo paths, `CSRF_SECRET`, SMTP settings, session cookie setup) — session_start() happens here.
5. Default login after a fresh schema import: `admin` / `changeme123`.

Quick syntax check for a single file: `php -l path/to/file.php`. There is no automated test runner — verify changes by exercising the page/API in a browser or with `curl`.

## Architecture

**Two layers per feature:** a top-level `*.php` page (server-rendered shell + big inline `<script>` block using vanilla JS/fetch) and a matching `api/*.php` REST-ish JSON endpoint it calls via `assets/js/app.js`'s `apiCall(endpoint, method, data)`. `meetings.php` is the largest page (~2,700 lines) — it's a single-page-app-style shell with tab panels (`tab-agenda`, `tab-attendees`, `tab-minutes`, `tab-resolutions`, procedural proposals) and multiple modals, driven entirely by client-side JS calling several API endpoints (`agenda.php`, `attendees.php`, `minutes.php`, `resolutions.php`, `procedural_proposals.php`, `documents.php`).

**Every page** starts with `require_once __DIR__ . '/includes/header.php'` → pulls in `config/auth.php` → calls `requireLogin()`. `outputHeader($title, $activePage)` / `outputFooter()` render the shared chrome; `getAuthJsVars()` serializes the current user + a `permissions` map into JSON for use as `authData` in client JS (`hasPermission('...')` in `app.js` reads this).

**Every `api/*.php` endpoint** follows the same boilerplate: set JSON content-type + CORS headers, handle `OPTIONS` early, `require_once` auth.php + database.php, call `requireAuth()` (401 if not logged in), branch on `$_SERVER['REQUEST_METHOD']` (GET/POST/PUT/DELETE), and call `requirePermission('action_name')` before any write. All queries use PDO prepared statements from `getDBConnection()` — never build SQL by concatenating request input.

**Auth & permissions** (`config/auth.php`): role hierarchy `Viewer(1) < Member(2) < Clerk(3) < Admin(4)`, defined once in the `ROLE_HIERARCHY` constant. Fine-grained actions (`manage_agenda`, `create_resolution`, `delete_meeting`, etc.) are defined in the `PERMISSIONS` array as `{action: [allowed roles]}`. Adding a new mutating action means adding it to `PERMISSIONS`, gating the API branch with `requirePermission('the_action')`, and (if it should affect UI visibility) adding it to `getAuthJsVars()` in `includes/header.php` and checking it with `hasPermission()` in JS. 2FA (TOTP via `libs/phpqrcode`) is configured in `config/twofactor.php`.

**API key auth** (non-browser clients, e.g. the Word minutes macros in `word macro/`): an `X-API-Key` header is checked centrally in `isLoggedIn()`/`getCurrentUser()`/`getCurrentRole()` in `config/auth.php` (via `currentApiKeyUser()`), so every `api/*.php` endpoint supports it automatically through the existing `requireAuth()`/`requirePermission()` calls — no per-endpoint changes needed. A key authenticates as the `users` row it was generated for and inherits that user's role/permissions; only a SHA-256 hash is stored (`api_keys` table), never the raw key. Generate one with `php database/generate_api_key.php <username> <label>` — it prints the raw key once and cannot be recovered afterwards.

**Shared domain logic lives in `includes/`, not duplicated per-endpoint:**
- `agenda_helpers.php` — attaches resolution data to agenda items for display/export, item-numbering helpers.
- `quorum_helpers.php` — quorum present/required/met calculation against `meeting_type_members` (UCA Manual for Meetings 5.1/5.3 rules); `recalculateQuorum()` persists `quorum_met` on the `meetings` row and should be called whenever attendance changes.
- `resolution_helpers.php` — resolution data validation and outcome/vote-detail rendering shared between the API and PDF/HTML exports.

**Agenda item numbering**: items are numbered `YY.M.SEQ` (or with a letter suffix for sub-items via `numberToLetterSuffix()`), generated in `api/agenda.php`. Reordering is a single bulk `POST` with `{"action": "reorder", "meeting_id": X, "order": [id1, id2, ...]}` rather than per-item PUTs — preserve this contract if touching agenda ordering. Agenda items support one level of hierarchy (`parent_id`/`sub_position`).

**Documents** are SharePoint links, not local file uploads — `POST /api/documents.php` takes a JSON body with `sharepoint_url` (validated server-side as HTTPS + SharePoint domain); `download.php`/`view_pdf.php` just redirect to that URL. Don't reintroduce local multipart upload handling for documents; the `uploads/` directory and `MAX_FILE_SIZE`/`ALLOWED_FILE_TYPES` constants are legacy from before this migration.

**Exports** (`export/*.php`) render agenda/minutes/notices as HTML or PDF (TCPDF). `export/agenda_pdf.php` additionally merges attached PDF documents using whichever of `pdftk` / `ghostscript` / `pdfunite` is available on the system, falling back to `setasign/fpdi` if installed via Composer, and degrading gracefully (agenda still exports, just unmerged) if none are present — preserve all three fallbacks and the temp-file cleanup when editing.

**Database**: `database/schema.sql` is the canonical current schema for fresh installs; `database/migration_*.sql` are incremental, one-purpose-each upgrade scripts for existing installs (naming pattern `migration_add_<feature>.sql` / `migration_<change>.sql`). When changing the schema: update `schema.sql` AND add a new migration file — don't only do one. `database/fix_role_enum.php` is a one-off PHP helper for a specific enum-widening fix, not a general migration pattern.

**Recent feature note**: decision method / procedural proposals (`api/procedural_proposals.php`, `migration_procedural_proposal_timing.sql`) is a newer addition layered onto the resolutions/minutes flow inside `meetings.php` — check `resolution_helpers.php` and the `manage_procedural_proposals` permission when extending it.

## Conventions to preserve

- API responses are always JSON: success payloads are the resource/list directly; errors are `{"error": true, "message": "..."}` (some older endpoints use `{"error": "..."}` as a string — match whichever the endpoint you're editing already does) with HTTP status 400/401/403/404/500 as appropriate.
- All DB access goes through `getDBConnection()` + prepared statements; never trust `$_GET`/`$_POST`/`$_SERVER` values into SQL directly.
- Output escaping: use `htmlspecialchars()` for any user-controlled string rendered into HTML (see `includes/header.php` for the pattern).
- Timezone is fixed to `Australia/Sydney` (`date_default_timezone_set` in `config/auth.php`) — don't change this per-feature.
- Session cookies are httponly, SameSite=Lax, and secure-if-HTTPS-detected (including behind Cloudflare via `X-Forwarded-Proto`/`CF-Visitor`) — this is deliberate for the deployment target; don't relax it.
