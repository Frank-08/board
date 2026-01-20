# Together in Council Meeting Management System

A comprehensive LAMP (Linux, Apache, MySQL, PHP) stack application for managing governance boards, board members, meetings, agendas, minutes, and resolutions.

## Features

- **Meeting Type Management**: Manage multiple meeting types (e.g., Standing Committee, Presbytery in Council, PRC, RPC, Workshop)
- **Board Members**: Track board members with roles per meeting type, status, and contact information. Members can belong to multiple meeting types with different roles in each.
- **Meetings**: Schedule and manage board meetings with various meeting types
- **Agendas**: 
  - Create and manage meeting agendas with items, presenters, and duration
  - **Agenda Templates**: Define default agenda items per meeting type that auto-populate new meetings
  - **Drag-and-drop reordering**: Reorder agenda items by dragging or using up/down arrow buttons
  - **Automatic item numbering**: Items are automatically numbered in YY.MM.SEQ format
  - **PDF document attachments**: Attach PDF documents to agenda items (PDF-only for agenda items)
- **Attendees**: Track meeting attendance and participation
- **Minutes**: Create, review, and approve meeting minutes
  - **Minutes Comments**: Add detailed comments to individual agenda items within meeting minutes
- **Resolutions**: Manage board resolutions with voting records and status
- **Document Management**: Upload and manage documents, with PDF support for agenda attachments
- **PDF Exports**: 
  - Export agendas as HTML or combined PDF
  - **Combined PDF exports**: Merge agenda with attached PDF documents into a single PDF file
  - **Logo support**: Add your organization logo to PDF exports
  - **Meeting Notices**: Generate meeting notices as HTML or PDF for participant distribution
- **Two-Factor Authentication**: TOTP-based 2FA with authenticator app support and backup codes
- **Password Reset**: Email-based password reset with CSRF protection
- **Dashboard**: Overview of key statistics and upcoming events

## Requirements

- Linux operating system
- Apache web server (2.4+)
- MySQL/MariaDB (5.7+ or 10.2+)
- PHP (7.4+ or 8.0+)
- PHP extensions: PDO, PDO_MySQL, JSON
- **Optional for PDF merging**: pdftk, ghostscript (gs), or pdfunite (for combined PDF exports)

## Installation

### 1. Clone or Download the Project

```bash
cd /var/www/html
# or your Apache document root
git clone <repository-url> board
cd board
```

### 2. Set Permissions

```bash
chmod 755 .
chmod -R 755 api config assets
mkdir -p uploads
chmod 777 uploads
```

### 3. Database Setup

Create the MySQL database and user:

```bash
mysql -u root -p
```

In MySQL console:

```sql
CREATE DATABASE governance_board CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'board_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON governance_board.* TO 'board_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Import the database schema:

```bash
mysql -u board_user -p governance_board < database/schema.sql
```

### 4. Configure Database Connection

Edit `config/database.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'governance_board');
define('DB_USER', 'board_user');
define('DB_PASS', 'your_secure_password');
```

### 4.5. Configure Logo (Optional)

Edit `config/config.php` to add your organization logo to PDF exports:

```php
define('LOGO_PATH', __DIR__ . '/../assets/images/logo.png');
define('LOGO_URL', 'assets/images/logo.png');
define('LOGO_WIDTH', 60); // Width in mm for PDF, px for HTML
define('LOGO_HEIGHT', 0);  // Height (0 = auto)
```

Place your logo file at `assets/images/logo.png` (or update the path accordingly).

### 4.6. Configure Email/SMTP (Required for Password Reset)

Edit `config/config.php` and configure SMTP settings for password reset and email notifications:

```php
define('SMTP_HOST', 'mail.example.com');           // Mail server address
define('SMTP_PORT', 587);                          // 587 for TLS, 465 for SSL, 25 for none
define('SMTP_USERNAME', 'your_email@example.com'); // SMTP username
define('SMTP_PASSWORD', 'your_password');          // SMTP password (use app-specific password for Gmail/Outlook/Zoho)
define('SMTP_FROM_EMAIL', 'your_email@example.com'); // Sender email address
define('SMTP_FROM_NAME', APP_NAME);                // Sender display name
define('SMTP_ENCRYPTION', 'tls');                  // 'tls', 'ssl', or '' for none
```

**Common SMTP Configurations:**
- **TLS (port 587)**: Standard secure connection, recommended for most providers
- **SSL (port 465)**: Secure connection with SSL wrapper
- **None (port 25)**: Unencrypted connection, use only on trusted networks

**Note on App-Specific Passwords:** Services like Gmail, Outlook, and Zoho require app-specific passwords instead of your account password. Generate these from your account security settings.

### 4.7. Generate CSRF Secret

For password reset security, generate a random CSRF secret:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Copy the output and update the CSRF_SECRET in `config/config.php`:

```php
define('CSRF_SECRET', 'paste_your_generated_secret_here');
```

### 5. Apache Configuration

#### Option A: Using Virtual Host (Recommended)

Create a virtual host configuration file:

```apache
<VirtualHost *:80>
    ServerName board.local
    DocumentRoot /var/www/html/board
    
    <Directory /var/www/html/board>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/board_error.log
    CustomLog ${APACHE_LOG_DIR}/board_access.log combined
</VirtualHost>
```

Enable the site and restart Apache:

```bash
sudo a2ensite board.conf
sudo systemctl restart apache2
```

Add to `/etc/hosts`:
```
127.0.0.1 board.local
```

#### Option B: Access via Document Root

If you placed the project in your Apache document root, access it directly:
- `http://localhost/board/`

### 6. PHP Configuration

Ensure PHP has the required extensions enabled. Check with:

```bash
php -m | grep -i pdo
```

If needed, install PHP extensions:

```bash
# Ubuntu/Debian
sudo apt-get install php-mysql php-pdo

# CentOS/RHEL
sudo yum install php-mysql php-pdo
```

### 6.5. PDF Library Setup (Optional, for Combined PDF Exports)

For combined PDF exports (merging agenda with attached PDFs), you can use one of:

**Option 1: System Tools (Recommended)**
- Install `pdftk` or `ghostscript` (gs) or `pdfunite`
```bash
# Ubuntu/Debian
sudo apt-get install pdftk
# or
sudo apt-get install ghostscript
# or
sudo apt-get install poppler-utils  # includes pdfunite

# CentOS/RHEL
sudo yum install pdftk
# or
sudo yum install ghostscript
```

**Option 2: PHP Library**
- Install FPDI library via Composer (if system tools unavailable)
```bash
composer require setasign/fpdi
```

The system will automatically use available tools. If none are available, the export will still work but won't merge attached PDFs.

### 7. Optional Features Setup

The following features require additional database migrations:

#### Two-Factor Authentication (2FA)

Enable TOTP-based two-factor authentication with authenticator apps and backup codes:

```bash
mysql -u board_user -p governance_board < database/migration_add_2fa.sql
```

For comprehensive 2FA setup and troubleshooting, see [2FA_README.md](2FA_README.md).

#### Password Reset System

Enable email-based password reset functionality (requires email configuration from step 4.6):

```bash
mysql -u board_user -p governance_board < database/migration_add_password_reset.sql
```

#### Minutes Agenda Comments

Enable per-agenda-item comments within meeting minutes for detailed record-keeping:

```bash
mysql -u board_user -p governance_board < database/migration_add_minutes_comments.sql
```

### 8. Verify Installation

1. Open your browser and navigate to the application URL
2. You should see the dashboard
3. A sample committee should already be created with test data

## Project Structure

```
board/
├── api/                         # API endpoints
│   ├── agenda.php               # Agenda items CRUD (includes reordering)
│   ├── agenda_templates.php     # Agenda templates for meeting types
│   ├── attendees.php            # Meeting attendees CRUD
│   ├── committee_members.php    # Committee member associations
│   ├── committees.php           # Committee management
│   ├── dashboard.php            # Dashboard statistics
│   ├── documents.php            # Document upload/management
│   ├── download.php             # Document download endpoint
│   ├── meeting_type_members.php # Meeting type membership management
│   ├── meeting_types.php        # Meeting type CRUD
│   ├── meetings.php             # Meetings CRUD
│   ├── members.php              # Board members CRUD
│   ├── minutes.php              # Meeting minutes CRUD
│   ├── minutes_comments.php     # Minutes agenda comments
│   ├── organizations.php        # Organization management
│   ├── password_reset.php       # Password reset API
│   ├── qrcode.php               # QR code generation for 2FA
│   ├── resolutions.php          # Resolutions CRUD
│   ├── users.php                # User management
│   └── view_pdf.php             # PDF viewer endpoint
├── assets/                      # Frontend assets
│   ├── css/
│   │   ├── pdf.css              # PDF export styles
│   │   └── style.css            # Main stylesheet
│   ├── images/                  # Images (logo, etc.)
│   └── js/
│       └── app.js               # JavaScript utilities
├── config/                      # Configuration files
│   ├── auth.php                 # Authentication functions
│   ├── config.php               # App configuration
│   ├── database.php             # Database connection
│   ├── email.php                # Email configuration and functions
│   └── twofactor.php            # 2FA configuration
├── database/                    # Database files
│   ├── schema.sql               # Database schema (current complete version)
│   ├── fix_role_enum.php        # Helper script for role enum fixes
│   └── migration_*.sql          # Database migration scripts (incremental updates)
├── export/                      # Export functionality
│   ├── agenda.php               # HTML agenda export
│   ├── agenda_pdf.php           # Combined PDF agenda export
│   ├── minutes.php              # Minutes export
│   ├── notice.php               # Meeting notice HTML export
│   └── notice_pdf.php           # Meeting notice PDF export
├── libs/                        # Third-party libraries
│   ├── phpqrcode/               # QR code generation library for 2FA
│   └── other/                   # Additional utility libraries
├── uploads/                     # File uploads directory (writable by webserver)
├── documents.php                # Documents management page
├── forgot_password.php          # Password reset request page
├── index.php                    # Dashboard page
├── login.php                    # Login page
├── logout.php                   # Logout handler
├── members.php                  # Board members page
├── meetings.php                 # Meetings page
├── reset_password.php           # Password reset completion page
├── resolutions.php              # Resolutions page
├── setup_2fa.php                # 2FA setup page
├── users.php                    # User management page
├── verify_2fa.php               # 2FA verification page
├── .htaccess                    # Apache configuration
├── .github/                     # GitHub configuration
│   └── copilot-instructions.md  # AI coding agent guidelines
└── README.md                    # This file
```

## Usage

### Getting Started

1. **Select or Create a Meeting Type**: Use the meeting type selector on the dashboard
2. **Add Board Members**: Navigate to Board Members and add your board members. You can assign members to multiple meeting types, each with their own role.
3. **Set Up Agenda Templates** (Optional): Click "Manage Agenda Template" to define default agenda items (e.g., Call to Order, Approval of Minutes) that auto-populate new meetings
4. **Schedule Meetings**: Go to Meetings and create your first board meeting (check "Apply default agenda template" to use your template)
5. **Create Agenda**: 
   - Add agenda items to your meetings
   - Reorder items by dragging them or using the up/down arrow buttons
   - Attach PDF documents to agenda items (PDF-only for agenda attachments)
6. **Track Attendance**: Add attendees and mark their attendance status
7. **Record Minutes**: Create meeting minutes after the meeting
8. **Manage Resolutions**: Record resolutions and voting outcomes
9. **Export Documents**: Export agendas, notices, and minutes as HTML or PDF

### Key Features

- **Dashboard**: Provides an overview of active members, upcoming meetings, pending resolutions, and draft minutes
- **Board Members**: Manage member profiles with roles (Chair, Deputy Chair, Secretary, Treasurer, Member, Ex-officio) across multiple meeting types
- **Meetings**: Full meeting lifecycle management from scheduling to completion
- **Agenda Management**: 
  - Organize meeting agendas with items, presenters, and time allocations
  - **Agenda Templates**: Define default agenda items per meeting type for quick meeting setup
  - Drag-and-drop or button-based reordering of agenda items
  - Automatic item numbering (YY.MM.SEQ format)
  - PDF document attachments (PDF-only for agenda items)
- **Minutes Management**: 
  - Create, review, approve, and publish meeting minutes
  - **Item-Specific Comments**: Add detailed comments to individual agenda items within minutes
  - Export minutes with all comments and decisions
- **Meeting Notices**: Generate HTML or PDF meeting notices for participant distribution with meeting details, date/time, location, virtual links, and status
- **PDF Exports**: 
  - Export agendas as HTML (print-friendly) or combined PDF
  - Attached PDF documents are merged into the agenda export
  - Custom logo support in all PDF exports
- **Resolutions**: Track board resolutions with voting records and status

## API Endpoints

All API endpoints return JSON and support standard HTTP methods (GET, POST, PUT, DELETE).

### API Authentication

All API endpoints use session-based authentication with secure httponly cookies. Session IDs are regenerated on login to prevent session fixation attacks. Authenticated requests include the session cookie, and responses include appropriate CORS headers.

### Error Responses

API errors return JSON with the following format:

```json
{
  "error": true,
  "message": "Description of the error"
}
```

HTTP status codes used:
- **200** - Success
- **400** - Bad request (missing or invalid parameters)
- **404** - Not found (resource doesn't exist)
- **500** - Server error

### Standard Endpoints

**Meeting Types:**
- `GET /api/meeting_types.php` - List all meeting types
- `GET /api/meeting_types.php?id={id}` - Get single meeting type
- `POST /api/meeting_types.php` - Create meeting type
- `PUT /api/meeting_types.php` - Update meeting type
- `DELETE /api/meeting_types.php` - Delete meeting type

**Meeting Type Members:**
- `GET /api/meeting_type_members.php?member_id={id}` - Get all meeting types for a member
- `GET /api/meeting_type_members.php?meeting_type_id={id}` - Get all members for a meeting type
- `POST /api/meeting_type_members.php` - Add member to meeting type
- `PUT /api/meeting_type_members.php` - Update member's role/status in meeting type
- `DELETE /api/meeting_type_members.php` - Remove member from meeting type

**Agenda Items:**
- `GET /api/agenda.php?meeting_id={id}` - Get all agenda items for a meeting
- `POST /api/agenda.php` - Create agenda item
- `POST /api/agenda.php` (with action=reorder) - Reorder agenda items in bulk
- `PUT /api/agenda.php` - Update agenda item
- `DELETE /api/agenda.php` - Delete agenda item

**Agenda Reordering Example:**
```json
POST /api/agenda.php
{
  "action": "reorder",
  "meeting_id": 12,
  "order": [45, 46, 44]
}
```

**Agenda Templates:**
- `GET /api/agenda_templates.php?meeting_type_id={id}` - Get agenda templates for a meeting type
- `POST /api/agenda_templates.php` - Create agenda template item
- `POST /api/agenda_templates.php` (action=reorder) - Reorder template items
- `PUT /api/agenda_templates.php` - Update template item
- `DELETE /api/agenda_templates.php` - Delete template item

**Documents:**
- `GET /api/documents.php?agenda_item_id={id}` - Get documents for an agenda item
- `GET /api/documents.php?meeting_id={id}` - Get documents for a meeting
- `POST /api/documents.php` - Upload document (PDF-only for agenda items)
- `GET /api/view_pdf.php?id={id}` - View PDF document inline
- `GET /api/download.php?id={id}` - Download document

**Minutes Comments:**
- `GET /api/minutes_comments.php?meeting_id={id}` - Get all comments for a meeting's minutes
- `GET /api/minutes_comments.php?agenda_item_id={id}` - Get comments for a specific agenda item
- `POST /api/minutes_comments.php` - Add comment to agenda item in minutes
- `PUT /api/minutes_comments.php` - Update a comment
- `DELETE /api/minutes_comments.php` - Delete a comment

**Other Endpoints:**
Similar endpoints exist for members, meetings, attendees, minutes, resolutions, users, and organizations. All support GET (list/retrieve), POST (create), PUT (update), and DELETE operations where applicable.

## Configuration

### Logo Configuration

To add your organization logo to PDF exports, edit `config/config.php`:

```php
// Logo settings for PDF exports
define('LOGO_PATH', __DIR__ . '/../assets/images/logo.png');  // Absolute file path
define('LOGO_URL', 'assets/images/logo.png');                  // Web-accessible URL
define('LOGO_WIDTH', 60);  // Width in mm (PDF) or px (HTML)
define('LOGO_HEIGHT', 0);  // Height (0 = auto)
```

Place your logo file at the specified path. Supported formats: PNG, JPG, JPEG, GIF.

### File Upload Configuration

Edit `config/config.php` to customize file uploads:

```php
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);
```

**Note**: When attaching documents to agenda items, only PDF files are allowed.

## Authentication

The system includes session-based authentication with role-based access control.

### Default Admin Account

After installation, log in with:
- **Username:** `admin`
- **Password:** `changeme123`

**IMPORTANT:** Change the default admin password immediately after first login!

### User Roles

| Role | Permissions |
|------|-------------|
| **Admin** | Full access: manage users, members, meeting types, delete anything |
| **Clerk** | Manage meetings, agendas, minutes, attendees, documents, resolutions |
| **Member** | View all, edit own attendance, limited writes |
| **Viewer** | Read-only access to all data |

### Adding Users

1. Log in as Admin
2. Navigate to Users page
3. Click "+ Add User"
4. Fill in username, password, email, and select a role
5. Optionally link the user to a board member

### Authentication Endpoints

- `login.php` - Login page
- `logout.php` - Logout and redirect to login
- `api/users.php` - User management API (Admin only)

## Security Considerations

1. **Change Default Admin Password**: Log in and change the default admin password immediately
2. **Change Database Passwords**: Update database credentials in `config/database.php`
3. **File Permissions**: Ensure sensitive files are not publicly accessible
4. **HTTPS**: Use HTTPS in production environments
5. **Input Validation**: All user inputs are sanitized through PDO prepared statements
6. **SQL Injection**: Protected by PDO prepared statements
7. **XSS Protection**: Output is escaped in the frontend
8. **File Upload Security**: File types are validated, and agenda item attachments are restricted to PDFs
9. **Session Security**: Sessions use httponly cookies and regenerate IDs on login
10. **CSRF Protection**: Login form includes CSRF token validation

## Troubleshooting

### Database Connection Errors

- Verify database credentials in `config/database.php`
- Ensure MySQL service is running: `sudo systemctl status mysql`
- Check database exists: `mysql -u root -p -e "SHOW DATABASES;"`

### Permission Errors

- Ensure Apache user (www-data) has read access to files
- Check uploads directory permissions: `chmod 777 uploads`

### PHP Errors

- Enable error logging in `.htaccess` or `php.ini`
- Check Apache error logs: `/var/log/apache2/error.log`

### Cannot Set Role as "Deputy Chair"

If you're unable to set a member's role to "Deputy Chair" or "Ex-officio", your database schema may need updating. Run one of these:

**Option 1: PHP Script (Recommended)**
```bash
php database/fix_role_enum.php
```

**Option 2: SQL Migration**
```bash
mysql -u root -p governance_board < database/migration_add_deputy_chair.sql
```

**Option 3: Manual SQL**
```sql
USE governance_board;
ALTER TABLE board_members 
MODIFY COLUMN role ENUM('Chair', 'Deputy Chair', 'Secretary', 'Treasurer', 'Member', 'Ex-officio') 
DEFAULT 'Member';
```

### 404 Errors

- Verify `.htaccess` is present and enabled
- Check Apache `AllowOverride All` is set
- Ensure mod_rewrite is enabled: `sudo a2enmod rewrite`

### Email Not Sending

If password reset emails or other notifications aren't being sent:
- Verify SMTP configuration in `config/config.php` with correct SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD
- Confirm username and password are correct for your email provider
- Verify port and encryption type match your email provider (587 for TLS, 465 for SSL, 25 for none)
- Check that your server's network allows outbound SMTP connections
- Review email provider's firewall/security rules to ensure connections from your server are allowed

### PDF Merge Failures

If PDF exports fail to merge attached documents:
- Install one of the required tools: `pdftk`, `ghostscript` (gs), or `pdfunite` using your package manager
- Alternatively, install the PHP FPDI library via Composer: `composer require setasign/fpdi`
- Verify installed tool is in the system PATH
- Check PHP error logs for specific merge tool errors
- Note: Exports will still work without merge tools, but attached PDFs won't be combined

### Document Upload Issues

If you cannot upload documents:
- Verify the `uploads/` directory exists and is writable by the web server user (www-data)
- Check that file size is under the MAX_FILE_SIZE limit configured in `config/config.php`
- For agenda item attachments, ensure the file is in PDF format only
- Verify your PHP installation's `upload_max_filesize` and `post_max_size` settings allow the file size
- Check browser console and server error logs for specific error messages

### 2FA QR Code Not Displaying

If the QR code doesn't appear during 2FA setup:
- Verify the `libs/` directory contains the phpqrcode library files
- Check PHP error logs for QR generation errors
- Ensure the `/api/qrcode.php` endpoint is accessible
- Use the manual secret key entry option as an alternative (shown below the QR code)
- Verify your authenticator app supports manual key entry (all major apps do)

## Development

### Adding New Features

1. Create API endpoint in `api/` directory
2. Add frontend page or extend existing pages
3. Update database schema if needed
4. Test thoroughly

### Database Migrations

When making schema changes:
1. Backup database: `mysqldump -u user -p governance_board > backup.sql`
2. The `database/schema.sql` file contains the complete current database schema
3. Migration files in the `database/` directory provide incremental updates for upgrading from older versions

**Applying a Migration:**
```bash
mysql -u board_user -p governance_board < database/migration_name.sql
```

**Available Migrations (by category):**

*Authentication:*
- `migration_add_2fa.sql` - Adds TOTP-based two-factor authentication
- `migration_add_password_reset.sql` - Adds password reset functionality
- `migration_add_users.sql` - Adds user management system
- `migration_fix_users_table.sql` - Updates user table schema

*Agenda Features:*
- `migration_add_agenda_templates.sql` - Adds agenda templates per meeting type
- `migration_add_item_number.sql` - Adds automatic item numbering (YY.MM.SEQ format)
- `migration_add_parent_to_agenda.sql` - Adds hierarchical agenda items (sub-items)
- `migration_add_agenda_item_to_documents.sql` - Links documents to agenda items

*Meeting Management:*
- `migration_committees_to_meeting_types.sql` - Migrates from committees to meeting types
- `migration_add_minutes_comments.sql` - Adds per-item comments in minutes
- `migration_add_deputy_chair.sql` - Adds "Deputy Chair" role option

*Schema Updates:*
- `migration_organizations_to_committees.sql` - Reorganizes organization/committee structure
- `migration_rename_pic_to_presbytery_in_council.sql` - Renames "PiC" to "Presbytery in Council"
- `migration_remove_agenda_status.sql` - Removes deprecated agenda status field
- `migration_update_resolutions_schema.sql` - Updates resolutions table structure
- `migration_fix_role_enum.sql` - Fixes role enum constraints

For new installations, import `database/schema.sql` directly. For upgrades from older versions, review the migration history and apply needed migrations in order.

## License

This project is open source and available for use and modification.

## Support

For issues, questions, or contributions, please refer to the project repository.

## Changelog

### Version 1.3.0
- **Two-Factor Authentication (2FA)**: TOTP-based authentication using any authenticator app (Google Authenticator, Authy, Microsoft Authenticator, 1Password, etc.), QR code generation for easy setup, 10 one-time backup codes for account recovery
- **Password Reset System**: Email-based password reset with secure tokens and CSRF protection, configurable token expiration
- **Email Integration**: Full SMTP configuration support, HTML email templates for password reset and notifications, sendEmail helper functions for future integrations
- **Session Security**: Enhanced session handling with httponly cookies, HTTPS detection for proxy environments (Cloudflare compatibility), session ID regeneration on login

### Version 1.2.5
- **Minutes Agenda Comments**: Add detailed comments to individual agenda items within meeting minutes, separate from general meeting notes, comments included in minute exports and PDFs

### Version 1.2.0
- **Agenda Templates**: Define default agenda items per meeting type that auto-populate when creating new meetings
- **Edit Resolutions from Minutes**: Edit resolutions directly from the Minutes tab
- **Meeting Notice Exports**: Generate meeting notices as HTML or PDF with meeting details, date/time, location, virtual links, and status

### Version 1.1.0
- **Agenda Item Reordering**: Drag-and-drop and up/down arrow buttons for reordering agenda items
- **Automatic Item Numbering**: Agenda items automatically numbered in YY.MM.SEQ format
- **PDF Document Attachments**: Support for attaching PDF documents to agenda items (PDF-only for agenda attachments)
- **Combined PDF Exports**: Export agendas as combined PDF with attached documents merged
- **Logo Support**: Add organization logo to PDF exports
- **Meeting Types**: Migrated from committees to meeting types for better flexibility
- **Enhanced Document Management**: Improved file lookup and PDF viewing capabilities

### Version 1.0.0
- Initial release
- Organization management
- Board member management
- Meeting scheduling and management
- Agenda management
- Attendance tracking
- Minutes creation and approval
- Resolution management
- Dashboard with statistics

## Additional Documentation

For more detailed information about specific features and configurations, refer to these additional documentation files:

- **[2FA_README.md](2FA_README.md)** - Comprehensive guide to setting up and troubleshooting Two-Factor Authentication
- **[MIGRATION_GUIDE.md](MIGRATION_GUIDE.md)** - Step-by-step instructions for upgrading from older versions
- **[VERIFICATION_REPORT.md](VERIFICATION_REPORT.md)** - Database schema verification and integrity checks
- **[userguide.md](userguide.md)** - Complete end-user manual with step-by-step instructions for all features

