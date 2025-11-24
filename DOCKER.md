# Docker Setup Guide

This guide explains how to run the Governance Board Management System using Docker.

## Prerequisites

- Docker Engine 20.10 or higher
- Docker Compose 2.0 or higher

## Quick Start (Development)

1. **Copy environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` file** (optional, defaults work for development):
   ```bash
   nano .env
   ```

3. **Start the environment:**
   ```bash
   ./docker/start.sh
   ```
   Or manually:
   ```bash
   docker-compose up -d
   ```

4. **Access the application:**
   - Web: http://localhost:8080
   - Database: localhost:3306

## Production Deployment

1. **Create production `.env` file:**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` with production values:**
   - Set strong passwords for `DB_PASS` and `MYSQL_ROOT_PASSWORD`
   - Update `BASE_URL` to your domain
   - Set `APP_ENV=production`

3. **Start production environment:**
   ```bash
   ./docker/prod-start.sh
   ```
   Or manually:
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```

4. **Access the application:**
   - Web: http://localhost (or your configured domain)

## Environment Variables

Key environment variables in `.env`:

- `DB_HOST`: Database host (use `db` for Docker)
- `DB_NAME`: Database name (default: `governance_board`)
- `DB_USER`: Database user (default: `board_user`)
- `DB_PASS`: Database password
- `MYSQL_ROOT_PASSWORD`: MySQL root password
- `APP_ENV`: Environment (`development` or `production`)
- `BASE_URL`: Application base URL

## Common Commands

### Development

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f

# View web logs only
docker-compose logs -f web

# Rebuild images
docker-compose build

# Execute command in web container
docker-compose exec web bash

# Execute command in database container
docker-compose exec db mysql -u board_user -p governance_board
```

### Production

```bash
# Start containers
docker-compose -f docker-compose.prod.yml up -d

# Stop containers
docker-compose -f docker-compose.prod.yml down

# View logs
docker-compose -f docker-compose.prod.yml logs -f

# Rebuild images
docker-compose -f docker-compose.prod.yml build
```

## Database Management

### Initial Setup

The database schema is automatically imported on first run from `database/schema.sql`.

### Running Migrations

To run database migrations:

```bash
# Copy migration file to container
docker cp database/migration_name.sql board_db:/tmp/

# Execute migration
docker-compose exec db mysql -u board_user -p governance_board < /tmp/migration_name.sql
```

Or from host:

```bash
docker-compose exec db mysql -u board_user -p governance_board < database/migration_name.sql
```

### Database Backup

```bash
# Backup database
docker-compose exec db mysqldump -u board_user -p governance_board > backup_$(date +%Y%m%d).sql

# Restore database
docker-compose exec -T db mysql -u board_user -p governance_board < backup_20240101.sql
```

### Production Backup

```bash
# Backup database
docker-compose -f docker-compose.prod.yml exec db mysqldump -u board_user -p governance_board > backup_$(date +%Y%m%d).sql

# Backup is also stored in db_backup volume
```

## File Uploads

Uploads are stored in the `uploads/` directory, which is mounted as a volume.

- **Development**: Files are stored in `./uploads` on the host
- **Production**: Files are stored in a Docker volume for persistence

## Troubleshooting

### Containers won't start

1. Check logs:
   ```bash
   docker-compose logs
   ```

2. Verify ports are not in use:
   ```bash
   # Development
   lsof -i :8080
   lsof -i :3306
   
   # Production
   lsof -i :80
   ```

3. Check Docker is running:
   ```bash
   docker ps
   ```

### Database connection errors

1. Verify database container is healthy:
   ```bash
   docker-compose ps
   ```

2. Check database logs:
   ```bash
   docker-compose logs db
   ```

3. Verify environment variables in `.env` match database configuration

### Permission errors

If you encounter permission errors with uploads:

```bash
# Fix uploads directory permissions
docker-compose exec web chown -R www-data:www-data /var/www/html/uploads
docker-compose exec web chmod -R 777 /var/www/html/uploads
```

### Rebuild after code changes

```bash
# Rebuild and restart
docker-compose up -d --build
```

## Development Workflow

1. **Make code changes** - Files are mounted as volumes, so changes are reflected immediately
2. **Test in browser** - Access http://localhost:8080
3. **View logs** - `docker-compose logs -f web`
4. **Restart if needed** - `docker-compose restart web`

## Production Considerations

- **Security**: Use strong passwords in `.env`
- **SSL/TLS**: Use a reverse proxy (nginx/traefik) for HTTPS
- **Backups**: Set up automated database backups
- **Monitoring**: Consider adding health checks and monitoring
- **Updates**: Rebuild images when updating code:
  ```bash
  docker-compose -f docker-compose.prod.yml build
  docker-compose -f docker-compose.prod.yml up -d
  ```

## Volumes

### Development
- `./uploads` → `/var/www/html/uploads` (bind mount)
- `db_data` → MySQL data (named volume)

### Production
- `uploads_backup` → Upload backups (named volume)
- `db_data_prod` → MySQL data (named volume)
- `db_backup` → Database backups (named volume)

## Network

Containers communicate via the `board_network` bridge network. The web container connects to the database using the hostname `db`.

## Resource Limits (Production)

Production containers have resource limits:
- **Web**: 512MB memory limit, 1 CPU
- **Database**: 1GB memory limit, 1 CPU

Adjust in `docker-compose.prod.yml` if needed.

