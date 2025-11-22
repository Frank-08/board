# Governance Board Management System

A comprehensive LAMP (Linux, Apache, MySQL, PHP) stack application for managing governance boards, board members, meetings, agendas, minutes, and resolutions.

## Features

- **Organization Management**: Manage multiple organizations
- **Board Members**: Track board members with roles, status, and contact information
- **Meetings**: Schedule and manage board meetings with types (Regular, Special, Annual, Emergency, Workshop)
- **Agendas**: Create and manage meeting agendas with items, presenters, and duration
- **Attendees**: Track meeting attendance and participation
- **Minutes**: Create, review, and approve meeting minutes
- **Resolutions**: Manage board resolutions with voting records and status
- **Dashboard**: Overview of key statistics and upcoming events

## Requirements

- Linux operating system
- Apache web server (2.4+)
- MySQL/MariaDB (5.7+ or 10.2+)
- PHP (7.4+ or 8.0+)
- PHP extensions: PDO, PDO_MySQL, JSON

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

### 7. Verify Installation

1. Open your browser and navigate to the application URL
2. You should see the dashboard
3. A sample organization should already be created with test data

## Project Structure

```
board/
├── api/                    # API endpoints
│   ├── organizations.php   # Organization CRUD
│   ├── members.php         # Board members CRUD
│   ├── meetings.php        # Meetings CRUD
│   ├── agenda.php          # Agenda items CRUD
│   ├── attendees.php       # Meeting attendees CRUD
│   ├── minutes.php         # Meeting minutes CRUD
│   ├── resolutions.php     # Resolutions CRUD
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
│   └── schema.sql          # Database schema
├── uploads/                # File uploads directory
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

1. **Select or Create an Organization**: Use the organization selector on the dashboard
2. **Add Board Members**: Navigate to Board Members and add your board members with their roles
3. **Schedule Meetings**: Go to Meetings and create your first board meeting
4. **Create Agenda**: Add agenda items to your meetings
5. **Track Attendance**: Add attendees and mark their attendance status
6. **Record Minutes**: Create meeting minutes after the meeting
7. **Manage Resolutions**: Record resolutions and voting outcomes

### Key Features

- **Dashboard**: Provides an overview of active members, upcoming meetings, pending resolutions, and draft minutes
- **Board Members**: Manage member profiles with roles (Chair, Deputy Chair, Secretary, Treasurer, Member, Ex-officio)
- **Meetings**: Full meeting lifecycle management from scheduling to completion
- **Agenda Management**: Organize meeting agendas with items, presenters, and time allocations
- **Minutes**: Create, review, approve, and publish meeting minutes
- **Resolutions**: Track board resolutions with voting records and status

## API Endpoints

All API endpoints return JSON and support standard HTTP methods:

- `GET /api/organizations.php` - List all organizations
- `GET /api/organizations.php?id={id}` - Get single organization
- `POST /api/organizations.php` - Create organization
- `PUT /api/organizations.php` - Update organization
- `DELETE /api/organizations.php` - Delete organization

Similar endpoints exist for members, meetings, agenda items, attendees, minutes, and resolutions.

## Security Considerations

1. **Change Default Passwords**: Update database credentials in `config/database.php`
2. **File Permissions**: Ensure sensitive files are not publicly accessible
3. **HTTPS**: Use HTTPS in production environments
4. **Input Validation**: All user inputs are sanitized through PDO prepared statements
5. **SQL Injection**: Protected by PDO prepared statements
6. **XSS Protection**: Output is escaped in the frontend

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

## License

This project is open source and available for use and modification.

## Support

For issues, questions, or contributions, please refer to the project repository.

## Changelog

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

