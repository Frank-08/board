#!/bin/bash
# Start production environment

echo "Starting Docker production environment..."

# Check if .env exists
if [ ! -f .env ]; then
    echo "ERROR: .env file is required for production!"
    echo "Please create .env file with production configuration."
    exit 1
fi

# Validate required environment variables
if ! grep -q "DB_PASS=" .env || ! grep -q "MYSQL_ROOT_PASSWORD=" .env; then
    echo "ERROR: DB_PASS and MYSQL_ROOT_PASSWORD must be set in .env for production!"
    exit 1
fi

# Start containers
docker-compose -f docker-compose.prod.yml up -d

echo "Waiting for database to be ready..."
sleep 10

# Check if containers are running
if docker-compose -f docker-compose.prod.yml ps | grep -q "Up"; then
    echo "✓ Production containers are running!"
    echo "  Web: http://localhost"
    echo ""
    echo "To view logs: docker-compose -f docker-compose.prod.yml logs -f"
    echo "To stop: docker-compose -f docker-compose.prod.yml down"
else
    echo "✗ Failed to start containers. Check logs with: docker-compose -f docker-compose.prod.yml logs"
    exit 1
fi

