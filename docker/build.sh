#!/bin/bash
# Build Docker images

echo "Building Docker images..."

# Build web image
docker-compose build

echo "✓ Build complete!"
echo ""
echo "To start containers: docker-compose up -d"
echo "Or use: ./docker/start.sh"

