Progress: I've inspected the repo for auth, user schema, and email hooks and collected required changes and security guidance.

## Plan: Add Forgot Password Flow

Brief TL;DR — Add a public request page, generate a secure token stored as a hash in the DB, email a reset link, and add a reset page + endpoint that validates the token, updates the password with `password_hash()`, marks the token used, and invalidates sessions. Add SMTP config and a DB migration for token fields.

### Steps
1. Add migration: create `database/migration_add_password_reset_columns.sql` with ALTER TABLE to add `password_reset_token_hash`, `password_reset_expires`, `password_reset_used`, `password_reset_requested_at`.
2. Add config: update `config/config.php` with SMTP/email settings and `PASSWORD_RESET_EXPIRE_SECONDS` (e.g., 3600).
3. Add email helper: add `config/email.php` (or vendor integration) to send authenticated SMTP emails (PHPMailer or API).
4. Add UI pages: create `forgot_password.php` (email form) and `reset_password.php` (token + new password form). Link `forgot_password.php` from `login.php`.
5. Add API endpoints: `api/password_reset_request.php` to accept email, rate-limit, generate secure token, store only token hash + expiry, and send email; `api/password_reset.php` to accept token+new password, verify hash+expiry with `hash_equals()`, call existing password update helper, mark token used, and revoke sessions.
6. Add logging and rate-limiting: log requests, throttle per IP/account (e.g., 3/hour), and return a generic success message to the requester.

### Further Considerations
1. Email provider: Option A — use PHPMailer with SMTP config; Option B — use SendGrid/Mailgun/SES API for higher deliverability.
2. Token storage: store only a secure hash (SHA-256 HMAC or hash with secret pepper); keep token expiry short (1 hour) and single-use.
3. User enumeration: always return the same response for existing/non-existing emails to avoid disclosure.

Would you like the migration SQL and a detailed per-file change checklist next (with exact SQL and HTTP request/response examples)?
