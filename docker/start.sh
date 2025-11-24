#!/bin/bash
# Start development environment

echo "Starting Docker development environment..."

# Check if .env exists
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
    echo "Please edit .env file with your configuration before continuing."
fi

# Start containers
docker-compose up -d

echo "Waiting for database to be ready..."
sleep 5

# Check if containers are running
if docker-compose ps | grep -q "Up"; then
    echo "✓ Containers are running!"
    echo "  Web: http://localhost:8080"
    echo "  Database: localhost:3306"
    echo ""
    echo "To view logs: docker-compose logs -f"
    echo "To stop: docker-compose down"
else
    echo "✗ Failed to start containers. Check logs with: docker-compose logs"
    exit 1
fi

