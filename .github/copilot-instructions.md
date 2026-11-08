<!-- Repository-specific Copilot instructions for contributors and AI coding agents -->
# Guidance for AI coding agents

This repository is a LAMP-stack PHP application for managing board meetings, agendas, documents and exports. The instructions below focus on concrete, discoverable patterns and workflows an agent should follow to be immediately productive.

- **Big picture:** The app is server-rendered PHP with a small JSON API under `api/` and export scripts under `export/`. Persistent state is MySQL (`database/schema.sql`). Frontend assets live in `assets/`. Documents are SharePoint links stored in the `documents` table — there is no local file storage.

- **Config & DB access:** Database constants and `getDBConnection()` live in `config/database.php` — use this function for DB access (it returns a PDO connection with exceptions enabled). Global app settings (including the `SHAREPOINT_TENANTS` allow-list) are in `config/config.php`.

- **API conventions:** Each file in `api/` is a self-contained REST-like endpoint that:
  - sets `Content-Type: application/json` and CORS headers (see `api/agenda.php`, `api/documents.php`),
  - inspects `$_SERVER['REQUEST_METHOD']` for GET/POST/PUT/DELETE, and
  - uses prepared statements via PDO from `getDBConnection()`.

- **Important patterns to preserve**
  - Item numbering: agenda items use item numbers in `YY.M.SEQ` format generated in `api/agenda.php` (see the sequence logic and reorder flow).
  - Reorder flow: reorder requests are POST JSON with `{ "action": "reorder", "meeting_id": X, "order": [id1,id2,...] }` (see `api/agenda.php`).
  - Documents: `api/documents.php` takes a JSON body with `sharepoint_url`, validated by `normaliseSharePointUrl()` in `includes/document_helpers.php`. `api/download.php` / `api/view_pdf.php` re-validate and redirect. Do not add local file upload handling.

- **Exports:** `export/agenda_pdf.php` builds an agenda-only PDF (TCPDF) via a temp file; attached documents stay in SharePoint and are not merged.

- **Migrations & helper scripts:** Database migrations live in `database/`. There are helper scripts such as `database/fix_role_enum.php` for common schema fixes. Use SQL migration files (e.g., `database/migration_add_deputy_chair.sql`) for schema changes and update `database/schema.sql` accordingly.

- **Composer / vendor libs:** The project may optionally use Composer-installed libraries under `vendor/` (TCPDF, Anthropic SDK). If adding PHP packages, update `composer.json` (create if missing) and document installation in `README.md`.

- **Concrete examples**
  - Reorder request (JSON POST to `api/agenda.php`):

    {
      "action": "reorder",
      "meeting_id": 12,
      "order": [45, 46, 44]
    }

  - Link a SharePoint document to an agenda item (JSON POST to `api/documents.php`):

    { "title": "Finance report", "meeting_id": 12, "agenda_item_id": 45, "sharepoint_url": "https://contoso.sharepoint.com/sites/board/Shared%20Documents/report.pdf" }

- **Permissions & environment**
  - For PDF exports, TCPDF must be installed (`vendor/` or one of the paths checked in `export/agenda_pdf.php`).

- **Testing & debugging hints**
  - Check Apache/PHP error logs for runtime errors. The README documents the common log locations.
  - DB errors are logged and often returned as 500 JSON responses from `getDBConnection()`; inspect `config/database.php` for the error handling behavior.

- **Files to reference when making changes**
  - `api/agenda.php` — agenda item numbering & reorder logic
  - `api/documents.php` — upload handling and file validation
  - `export/agenda_pdf.php` — PDF generation & merging strategy
  - `config/config.php` and `config/database.php` — runtime constants and `getDBConnection()`
  - `database/schema.sql` and `database/*.sql` — canonical DB model and migrations

If anything in this file is unclear or you'd like more detail (for example, automated test commands or a sample `composer.json`), tell me which area to expand and I'll update this file.
