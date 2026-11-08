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
5. Default login after a fresh schema import: `admin` / `changeme123` — you are forced to change it on first sign-in (`must_change_password`).

**Deploying**: `scripts/deploy.sh <live dir> [--apply|--done]` rsyncs the checkout into the live docroot but never touches `config/config.php`, `config/database.php`, `vendor/`/Composer files, or the database. It runs `php -l` on the live config files (exits 1 on a syntax error) and warns when the repo's config files gained `define()`s the live copies lack, or changed / new migrations appeared since the last `--done` (recorded in `.git/`). So when a change needs a new config constant, give it a safe default when undefined (`defined('X') ? X : ...`) and mention it in the PR — it will not reach the live site until someone copies it by hand.

Quick syntax check for a single file: `php -l path/to/file.php`. There is no automated test runner — verify changes by exercising the page/API in a browser or with `curl`.

## Architecture

**Two layers per feature:** a top-level `*.php` page (server-rendered shell + big inline `<script>` block using vanilla JS/fetch) and a matching `api/*.php` REST-ish JSON endpoint it calls via `assets/js/app.js`'s `apiCall(endpoint, method, data)`. `meetings.php` is the largest page (~2,700 lines) — it's a single-page-app-style shell with tab panels (`tab-agenda`, `tab-attendees`, `tab-minutes`, `tab-resolutions`, procedural proposals) and multiple modals, driven entirely by client-side JS calling several API endpoints (`agenda.php`, `attendees.php`, `minutes.php`, `resolutions.php`, `procedural_proposals.php`, `documents.php`).

**Every page** starts with `require_once __DIR__ . '/includes/header.php'` → pulls in `config/auth.php` → calls `requireLogin()`. `outputHeader($title, $activePage)` / `outputFooter()` render the shared chrome; `getAuthJsVars()` serializes the current user + a `permissions` map into JSON for use as `authData` in client JS (`hasPermission('...')` in `app.js` reads this).

**Every `api/*.php` endpoint** follows the same boilerplate: set JSON content-type (no CORS headers — the API is same-origin only), handle `OPTIONS` early, `require_once` auth.php + database.php, call `requireAuth()` (401 if not logged in), branch on `$_SERVER['REQUEST_METHOD']` (GET/POST/PUT/DELETE), and call `requirePermission('action_name')` before any write. All queries use PDO prepared statements from `getDBConnection()` — never build SQL by concatenating request input.

**Auth & permissions** (`config/auth.php`): role hierarchy `Viewer(1) < Member(2) < Clerk(3) < Admin(4)`, defined once in the `ROLE_HIERARCHY` constant. `Assistant` (an automated helper, e.g. Claude via an API key) is ranked 1 so `hasRole()` never grants it anything; its abilities are listed explicitly in `PERMISSIONS` (view_*, `categorise_resolutions`, `manage_action_items`, `draft_minutes`, `submit_agenda_item`) — keep it out of any new permission unless it is deliberately meant to have it. Fine-grained actions (`manage_agenda`, `create_resolution`, `delete_meeting`, etc.) are defined in the `PERMISSIONS` array as `{action: [allowed roles]}`. Adding a new mutating action means adding it to `PERMISSIONS`, gating the API branch with `requirePermission('the_action')`, and (if it should affect UI visibility) adding it to `getAuthJsVars()` in `includes/header.php` and checking it with `hasPermission()` in JS. 2FA (TOTP via `libs/phpqrcode`) is configured in `config/twofactor.php`.

**Security plumbing in `config/auth.php`** (from the Nov 2026 pentest — keep these working when editing auth):
- `revalidateSession()` reloads the session's `users` row once per request (via `isLoggedIn()`/`getCurrentUser()`/`getCurrentRole()`), so deactivation/deletion/role changes apply immediately. Call `bumpSessionVersion($userId)` after anything that should sign a user out elsewhere (password change/reset, 2FA disabled, deactivation).
- `requireAuth()` also runs `requireSameOriginForWrites()` (CSRF: rejects cookie-authenticated POST/PUT/DELETE whose `Origin`/`Sec-Fetch-Site` is another site; API-key requests are exempt) and returns 403 `PASSWORD_CHANGE_REQUIRED` while `must_change_password` is set (`requireLogin()` redirects to `change_password.php`).
- Throttling uses the `auth_attempts` table (`recordAuthAttempt` / `countAuthAttempts` / `clearAuthAttempts`); every password-setting path must call `validatePasswordStrength()`.
- `sendSecurityHeaders()` sets CSP/HSTS/etc. from PHP because `deploy.sh` never syncs `.htaccess`. Images may only be `'self'` or `data:`; no external scripts/styles.
- Error responses: never echo `$e->getMessage()` — use `publicErrorMessage($e, 'Summary')`, which logs the detail and returns a reference id.
- Members' `email`/`phone` in API responses go through `redactMemberContacts()` (`view_member_contacts` permission).

**API key auth** (non-browser clients, e.g. the Word minutes macros in `word macro/`): an `X-API-Key` header is checked centrally in `isLoggedIn()`/`getCurrentUser()`/`getCurrentRole()` in `config/auth.php` (via `currentApiKeyUser()`), so every `api/*.php` endpoint supports it automatically through the existing `requireAuth()`/`requirePermission()` calls — no per-endpoint changes needed. A key authenticates as the `users` row it was generated for and inherits that user's role/permissions; only a SHA-256 hash is stored (`api_keys` table), never the raw key. Generate one with `php database/generate_api_key.php <username> <label>` — it prints the raw key once and cannot be recovered afterwards.

**Shared domain logic lives in `includes/`, not duplicated per-endpoint:**
- `agenda_helpers.php` — attaches resolution data to agenda items for display/export, item-numbering helpers.
- `quorum_helpers.php` — quorum present/required/met calculation against `meeting_type_members` (UCA Manual for Meetings 5.1/5.3 rules); `recalculateQuorum()` persists `quorum_met` on the `meetings` row and should be called whenever attendance changes.
- `resolution_helpers.php` — resolution data validation and outcome/vote-detail rendering shared between the API and PDF/HTML exports.

**Agenda item numbering**: items are numbered `YY.M.SEQ` (or with a letter suffix for sub-items via `numberToLetterSuffix()`), generated in `api/agenda.php`. Reordering is a single bulk `POST` with `{"action": "reorder", "meeting_id": X, "order": [id1, id2, ...]}` rather than per-item PUTs — preserve this contract if touching agenda ordering. Agenda items support one level of hierarchy (`parent_id`/`sub_position`).

**Documents** are SharePoint links, not local file uploads — `POST /api/documents.php` takes a JSON body with `sharepoint_url` (validated server-side as HTTPS + SharePoint domain); `download.php`/`view_pdf.php` re-validate it and redirect. `POST` and `PUT` need `upload_documents`. There is no local file storage at all: `sharepoint_url` is `NOT NULL`, the old `file_path`/`file_size`/`mime_type` columns and `uploads/` directory were removed (`migration_remove_local_document_storage.sql`); `file_name` remains as a display name derived from the URL. Don't reintroduce local upload handling.

**Agenda submissions**: members (`submit_agenda_item` permission) propose items for an upcoming `Scheduled` meeting via `submit_agenda.php` → `api/agenda_submissions.php`, with a SharePoint link to a report written in the Word template at `assets/templates/Agenda_Item_Report_Template.dotx`. Clerks (`manage_agenda`) accept or decline from the same page; accepting appends a top-level agenda item (numbered as in `api/agenda.php`), sets the submitter as presenter and links the report as a `Report` document. The template is generated — edit `scripts/build_report_template.js` and rerun `scripts/build_report_template.sh`, don't hand-edit the `.dotx`. SharePoint URL validation is shared in `includes/document_helpers.php`.

**Resolutions register**: `resolutions.php` lists every resolution across all meetings (filters: meeting type, status, category, date range, text) grouped by category / meeting type / year / status / decision method, backed by `GET api/resolutions.php?register=1` and `?categories=1`; `export/resolutions.php` renders the same grouping as a printable HTML page or a Word (`format=word`, HTML-as-.doc) download. Query/grouping logic is shared in `resolution_helpers.php` (`fetchResolutionRegister()`, `groupResolutionRegister()`). `resolutions.category` is free text; it can be bulk-set with `POST {"action": "set_category", "ids": [...], "category": ...}` under the `categorise_resolutions` permission, which deliberately bypasses the approved-minutes lock (it's an index, not part of what was resolved).

**Action items**: `action_items` table (owner = `assigned_member_id` or free-text `assigned_to_name`, `due_date`, `status` Open/In Progress/Done/Cancelled, optional `agenda_item_id`/`resolution_id` that must belong to the same meeting). `api/action_items.php` + shared `includes/action_item_helpers.php`; UI in `assets/js/action_items.js`, used by `action_items.php` and the meeting's Action Items tab. Not locked by approved minutes. `update_own_action_items` lets a member change status/notes only on items assigned to their linked `board_member_id`. The old free-text `minutes.action_items` column still exists and is still shown.

**Draft minutes**: `draft_minutes` (Assistant) may create minutes as Draft and edit content/comments only while status is Draft; status changes, approval and DELETE still need `manage_minutes` (checks at the top of `api/minutes.php` and `api/minutes_comments.php`).

**In-app Claude** (`api/claude.php`, `includes/claude_helpers.php`): "suggest categories" and "suggest action items". Gated by `canUseClaude()` — a username allow-list (`CLAUDE_ALLOWED_USERS` in `config/config.php`), not a role — plus the normal permission for the action. Returns suggestions only; the browser saves accepted ones through the regular endpoints. Uses the Anthropic PHP SDK from `vendor/` (optional Composer dependency, like TCPDF: there is no committed `composer.json`; install with `composer require anthropic-ai/sdk guzzlehttp/guzzle`), structured outputs, and server-side refusal fallbacks. The key comes from `ANTHROPIC_API_KEY` (env, else config constant). Model data in prompts is wrapped in tags and treated as data. Validate everything Claude returns against the meeting's own ids before it is returned.

**Exports** (`export/*.php`) render agenda/minutes/notices as HTML or PDF (TCPDF). `export/agenda_pdf.php` renders the agenda only (via a temp file it cleans up); attached documents stay in SharePoint and are not merged into the PDF.

**Database**: `database/schema.sql` is the canonical current schema for fresh installs; `database/migration_*.sql` are incremental, one-purpose-each upgrade scripts for existing installs (naming pattern `migration_add_<feature>.sql` / `migration_<change>.sql`). When changing the schema: update `schema.sql` AND add a new migration file — don't only do one. `database/fix_role_enum.php` is a one-off PHP helper for a specific enum-widening fix, not a general migration pattern.

**Recent feature note**: decision method / procedural proposals (`api/procedural_proposals.php`, `migration_procedural_proposal_timing.sql`) is a newer addition layered onto the resolutions/minutes flow inside `meetings.php` — check `resolution_helpers.php` and the `manage_procedural_proposals` permission when extending it.

## Conventions to preserve

- API responses are always JSON: success payloads are the resource/list directly; errors are `{"error": true, "message": "..."}` (some older endpoints use `{"error": "..."}` as a string — match whichever the endpoint you're editing already does) with HTTP status 400/401/403/404/500 as appropriate.
- All DB access goes through `getDBConnection()` + prepared statements; never trust `$_GET`/`$_POST`/`$_SERVER` values into SQL directly.
- Output escaping: use `htmlspecialchars()` for any user-controlled string rendered into HTML (see `includes/header.php` for the pattern). In client JS, every API value interpolated into an `innerHTML` template literal goes through `escapeHtml()` from `assets/js/app.js` (it escapes quotes too, so it's safe in quoted attributes); links built from data use `escapeHtml(safeUrl(url))`; document links go via `api/download.php?id=` (which re-validates the SharePoint URL). Never interpolate free text into inline `onclick="...('${x}')"` handlers — pass ids.
- SharePoint URLs: validate with `normaliseSharePointUrl()` (includes/document_helpers.php) and store what it returns; it rejects `\`/`@`/whitespace tricks and honours the `SHAREPOINT_TENANTS` allow-list.
- Timezone is fixed to `Australia/Sydney` (`date_default_timezone_set` in `config/auth.php`) — don't change this per-feature.
- Session cookies are httponly, SameSite=Lax, and secure-if-HTTPS-detected (including behind Cloudflare via `X-Forwarded-Proto`/`CF-Visitor`) — this is deliberate for the deployment target; don't relax it.
