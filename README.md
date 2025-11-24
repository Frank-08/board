# Governance Board Management System

A comprehensive LAMP (Linux, Apache, MySQL, PHP) stack application for managing governance boards, board members, meetings, agendas, minutes, and resolutions.

## Features

- **Meeting Type Management**: Manage multiple meeting types (e.g., Standing Committee, Presbytery in Council, PRC, RPC, Workshop)
- **Board Members**: Track board members with roles per meeting type, status, and contact information. Members can belong to multiple meeting types with different roles in each.
- **Meetings**: Schedule and manage board meetings with various meeting types
- **Agendas**: 
  - Create and manage meeting agendas with items, presenters, and duration
  - **Drag-and-drop reordering**: Reorder agenda items by dragging or using up/down arrow buttons
  - **Automatic item numbering**: Items are automatically numbered in YY.MM.SEQ format
  - **PDF document attachments**: Attach PDF documents to agenda items (PDF-only for agenda items)
- **Attendees**: Track meeting attendance and participation
- **Minutes**: Create, review, and approve meeting minutes
- **Resolutions**: Manage board resolutions with voting records and status
- **Document Management**: Upload and manage documents, with PDF support for agenda attachments
- **PDF Exports**: 
  - Export agendas as HTML or combined PDF
  - **Combined PDF exports**: Merge agenda with attached PDF documents into a single PDF file
  - **Logo support**: Add your organization logo to PDF exports
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

### 7. Verify Installation

1. Open your browser and navigate to the application URL
2. You should see the dashboard
3. A sample committee should already be created with test data

## Project Structure

```
board/
├── api/                    # API endpoints
│   ├── meeting_types.php   # Meeting type CRUD
│   ├── meeting_type_members.php   # Meeting type membership management
│   ├── members.php         # Board members CRUD
│   ├── meetings.php        # Meetings CRUD
│   ├── agenda.php          # Agenda items CRUD (includes reordering)
│   ├── attendees.php       # Meeting attendees CRUD
│   ├── minutes.php         # Meeting minutes CRUD
│   ├── resolutions.php     # Resolutions CRUD
│   ├── documents.php       # Document upload/management
│   ├── view_pdf.php        # PDF viewer endpoint
│   ├── download.php        # Document download endpoint
│   └── dashboard.php       # Dashboard statistics
├── assets/                 # Frontend assets
│   ├── css/
│   │   └── style.css       # Main stylesheet
│   └── js/
│       └── app.js          # JavaScript utilities
├── config/                 # Configuration files
│   ├── database.php        # Database connection
│   └── config.php          # App configuration
├── database/               # Database files
│   ├── schema.sql          # Database schema
│   └── migration_*.sql     # Database migration scripts
├── export/                 # Export functionality
│   ├── agenda.php          # HTML agenda export
│   ├── agenda_pdf.php      # Combined PDF agenda export
│   └── minutes.php         # Minutes export
├── uploads/                # File uploads directory
├── assets/                 # Frontend assets
│   ├── images/             # Images (logo, etc.)
│   ├── css/
│   └── js/
├── index.php               # Dashboard page
├── members.php             # Board members page
├── meetings.php            # Meetings page
├── resolutions.php         # Resolutions page
├── documents.php           # Documents page
├── .htaccess               # Apache configuration
└── README.md               # This file
```

## Usage

### Getting Started

1. **Select or Create a Meeting Type**: Use the meeting type selector on the dashboard
2. **Add Board Members**: Navigate to Board Members and add your board members. You can assign members to multiple meeting types, each with their own role.
3. **Schedule Meetings**: Go to Meetings and create your first board meeting
4. **Create Agenda**: 
   - Add agenda items to your meetings
   - Reorder items by dragging them or using the up/down arrow buttons
   - Attach PDF documents to agenda items (PDF-only for agenda attachments)
5. **Track Attendance**: Add attendees and mark their attendance status
6. **Record Minutes**: Create meeting minutes after the meeting
7. **Manage Resolutions**: Record resolutions and voting outcomes
8. **Export Documents**: Export agendas as HTML or combined PDF (with attached PDFs merged)

### Key Features

- **Dashboard**: Provides an overview of active members, upcoming meetings, pending resolutions, and draft minutes
- **Board Members**: Manage member profiles with roles (Chair, Deputy Chair, Secretary, Treasurer, Member, Ex-officio) across multiple meeting types
- **Meetings**: Full meeting lifecycle management from scheduling to completion
- **Agenda Management**: 
  - Organize meeting agendas with items, presenters, and time allocations
  - Drag-and-drop or button-based reordering of agenda items
  - Automatic item numbering (YY.MM.SEQ format)
  - PDF document attachments (PDF-only for agenda items)
- **PDF Exports**: 
  - Export agendas as HTML (print-friendly) or combined PDF
  - Attached PDF documents are merged into the agenda export
  - Custom logo support in PDF exports
- **Minutes**: Create, review, approve, and publish meeting minutes
- **Resolutions**: Track board resolutions with voting records and status

## API Endpoints

All API endpoints return JSON and support standard HTTP methods:

- `GET /api/meeting_types.php` - List all meeting types
- `GET /api/meeting_types.php?id={id}` - Get single meeting type
- `POST /api/meeting_types.php` - Create meeting type
- `PUT /api/meeting_types.php` - Update meeting type
- `DELETE /api/meeting_types.php` - Delete meeting type

- `GET /api/meeting_type_members.php?member_id={id}` - Get all meeting types for a member
- `GET /api/meeting_type_members.php?meeting_type_id={id}` - Get all members for a meeting type
- `POST /api/meeting_type_members.php` - Add member to meeting type
- `PUT /api/meeting_type_members.php` - Update member's role/status in meeting type
- `DELETE /api/meeting_type_members.php` - Remove member from meeting type

- `GET /api/agenda.php?meeting_id={id}` - Get all agenda items for a meeting
- `POST /api/agenda.php` - Create agenda item
- `POST /api/agenda.php` (action=reorder) - Reorder agenda items (bulk update)
- `PUT /api/agenda.php` - Update agenda item
- `DELETE /api/agenda.php` - Delete agenda item

- `GET /api/documents.php?agenda_item_id={id}` - Get documents for an agenda item
- `POST /api/documents.php` - Upload document (PDF-only for agenda items)
- `GET /api/view_pdf.php?id={id}` - View PDF document inline
- `GET /api/download.php?id={id}` - Download document

Similar endpoints exist for members, meetings, attendees, minutes, and resolutions.

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

## Security Considerations

1. **Change Default Passwords**: Update database credentials in `config/database.php`
2. **File Permissions**: Ensure sensitive files are not publicly accessible
3. **HTTPS**: Use HTTPS in production environments
4. **Input Validation**: All user inputs are sanitized through PDO prepared statements
5. **SQL Injection**: Protected by PDO prepared statements
6. **XSS Protection**: Output is escaped in the frontend
7. **File Upload Security**: File types are validated, and agenda item attachments are restricted to PDFs

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

## Development

### Adding New Features

1. Create API endpoint in `api/` directory
2. Add frontend page or extend existing pages
3. Update database schema if needed
4. Test thoroughly

### Database Migrations

When making schema changes:
1. Backup database: `mysqldump -u user -p governance_board > backup.sql`
2. Update `database/schema.sql`
3. Apply changes manually or create migration script

**Available Migrations:**
- `migration_rename_pic_to_presbytery_in_council.sql` - Renames "PiC" to "Presbytery in Council"
- `migration_committees_to_meeting_types.sql` - Migrates from committees to meeting types
- `migration_add_deputy_chair.sql` - Adds "Deputy Chair" role option
- `migration_add_item_number.sql` - Adds item numbering to agenda items
- And more in the `database/` directory

To apply a migration:
```bash
mysql -u board_user -p governance_board < database/migration_name.sql
```

## License

This project is open source and available for use and modification.

## Support

For issues, questions, or contributions, please refer to the project repository.

## Changelog

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

