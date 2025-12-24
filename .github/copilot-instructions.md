<!-- Repository-specific Copilot instructions for contributors and AI coding agents -->
# Guidance for AI coding agents

This repository is a LAMP-stack PHP application for managing board meetings, agendas, documents and exports. The instructions below focus on concrete, discoverable patterns and workflows an agent should follow to be immediately productive.

- **Big picture:** The app is server-rendered PHP with a small JSON API under `api/` and export scripts under `export/`. Persistent state is MySQL (`database/schema.sql`). Frontend assets live in `assets/` and uploaded files are stored in `uploads/`.

- **Config & DB access:** Database constants and `getDBConnection()` live in `config/database.php` — use this function for DB access (it returns a PDO connection with exceptions enabled). Global app settings and upload limits are in `config/config.php`.

- **API conventions:** Each file in `api/` is a self-contained REST-like endpoint that:
  - sets `Content-Type: application/json` and CORS headers (see `api/agenda.php`, `api/documents.php`),
  - inspects `$_SERVER['REQUEST_METHOD']` for GET/POST/PUT/DELETE, and
  - uses prepared statements via PDO from `getDBConnection()`.

- **Important patterns to preserve**
  - Item numbering: agenda items use item numbers in `YY.M.SEQ` format generated in `api/agenda.php` (see the sequence logic and reorder flow).
  - Reorder flow: reorder requests are POST JSON with `{ "action": "reorder", "meeting_id": X, "order": [id1,id2,...] }` (see `api/agenda.php`).
  - File uploads: file uploads are handled by `api/documents.php` (multipart `file` field). When `agenda_item_id` is set, only PDFs are allowed; file size and allowed types come from `config/config.php` (`MAX_FILE_SIZE`, `ALLOWED_FILE_TYPES`).
  - Upload directory: `UPLOAD_DIR` (defined in `config/config.php`) must exist and be writable by the webserver — many endpoints assume files are stored under that directory.

- **Export & PDF merging:** `export/agenda_pdf.php` builds an agenda PDF (TCPDF) and attempts to merge attached PDFs using system tools (`pdftk`, `gs`, `pdfunite`) or `setasign/fpdi` if available. When editing export code, keep these fallbacks and the temporary-file workflow intact.

- **Migrations & helper scripts:** Database migrations live in `database/`. There are helper scripts such as `database/fix_role_enum.php` for common schema fixes. Use SQL migration files (e.g., `database/migration_add_deputy_chair.sql`) for schema changes and update `database/schema.sql` accordingly.

- **Composer / vendor libs:** The project may optionally use Composer-installed libraries under `vendor/` (TCPDF, FPDI). If adding PHP packages, update `composer.json` (create if missing) and document installation in `README.md`.

- **Concrete examples**
  - Reorder request (JSON POST to `api/agenda.php`):

    {
      "action": "reorder",
      "meeting_id": 12,
      "order": [45, 46, 44]
    }

  - Upload PDF for agenda item (curl):

    curl -X POST -F "file=@path/to/doc.pdf" -F "meeting_id=12" -F "agenda_item_id=45" http://localhost/board/api/documents.php

- **Permissions & environment**
  - Ensure `uploads/` exists and is writable (the APIs check this and fail early).
  - For combined PDF exports, ensure one of the system merge tools is installed or vendor libs are present.

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
