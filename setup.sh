#!/bin/bash

# ==============================================================================
# CI Insights Dashboard - Project Setup Script
# ==============================================================================
# Purpose: Initialize Laravel project and Docker environment
# Usage: ./setup.sh
# Requirements: Docker, Docker Compose, Git
# ==============================================================================

set -e  # Exit on error
set -u  # Exit on undefined variable

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Print colored message
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check prerequisites
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        log_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    # Check if Docker daemon is running
    if ! docker info &> /dev/null; then
        log_error "Docker daemon is not running. Please start Docker."
        exit 1
    fi
    
    log_info "✓ All prerequisites met"
}

# Create project directory structure
create_project_structure() {
    log_info "Creating project directory structure..."
    
    # Create backend directory
    mkdir -p backend
    
    # Create Docker configuration directories
    mkdir -p docker/nginx/conf.d
    mkdir -p docker/php
    mkdir -p docker/mysql/init
    
    # Create log directories
    mkdir -p logs/nginx
    mkdir -p logs/php
    mkdir -p logs/mysql
    
    log_info "✓ Project structure created"
}

# Create Laravel project
create_laravel_project() {
    log_info "Creating Laravel 11 project..."
    
    if [ -f "backend/artisan" ]; then
        log_warn "Laravel project already exists, skipping creation"
        return
    fi
    
    # Create Laravel project using Composer in Docker
    docker run --rm \
        -v "$(pwd)/backend:/app" \
        composer:2.7 \
        create-project --prefer-dist laravel/laravel:^11.0 .
    
    log_info "✓ Laravel project created"
}

# Generate application key
generate_app_key() {
    log_info "Generating application key..."
    
    if [ -f "backend/.env" ]; then
        # Generate key using Docker
        docker run --rm \
            -v "$(pwd)/backend:/var/www" \
            -w /var/www \
            php:8.2-cli-alpine \
            php artisan key:generate --ansi
        
        log_info "✓ Application key generated"
    else
        log_warn ".env file not found, will generate key after container startup"
    fi
}

# Copy environment file
setup_environment() {
    log_info "Setting up environment configuration..."
    
    if [ ! -f "backend/.env" ]; then
        if [ -f "backend/.env.example" ]; then
            cp backend/.env.example backend/.env
            log_info "✓ .env file created from .env.example"
        else
            log_error ".env.example not found"
            exit 1
        fi
    else
        log_warn ".env file already exists, skipping"
    fi
}

# Update .env file with Docker configuration
update_env_config() {
    log_info "Updating .env configuration for Docker..."
    
    if [ -f "backend/.env" ]; then
        # Update database configuration
        sed -i.bak 's/DB_HOST=127.0.0.1/DB_HOST=mysql/' backend/.env
        sed -i.bak 's/DB_DATABASE=laravel/DB_DATABASE=ci_insights/' backend/.env
        sed -i.bak 's/DB_USERNAME=root/DB_USERNAME=ci_user/' backend/.env
        sed -i.bak 's/DB_PASSWORD=/DB_PASSWORD=ci_secure_password_change_in_prod/' backend/.env
        
        # Update Redis configuration
        sed -i.bak 's/REDIS_HOST=127.0.0.1/REDIS_HOST=redis/' backend/.env
        
        # Update cache and queue drivers
        sed -i.bak 's/CACHE_DRIVER=file/CACHE_DRIVER=redis/' backend/.env
        sed -i.bak 's/QUEUE_CONNECTION=sync/QUEUE_CONNECTION=redis/' backend/.env
        sed -i.bak 's/SESSION_DRIVER=file/SESSION_DRIVER=redis/' backend/.env
        
        # Add Meilisearch configuration if not exists
        if ! grep -q "SCOUT_DRIVER" backend/.env; then
            echo "" >> backend/.env
            echo "SCOUT_DRIVER=meilisearch" >> backend/.env
            echo "MEILISEARCH_HOST=http://meilisearch:7700" >> backend/.env
            echo "MEILISEARCH_KEY=masterKey_change_in_production" >> backend/.env
        fi
        
        # Remove backup file
        rm -f backend/.env.bak
        
        log_info "✓ .env configuration updated"
    fi
}

# Set proper permissions
set_permissions() {
    log_info "Setting proper permissions..."
    
    if [ -d "backend" ]; then
        # Set ownership (adjust UID/GID based on your system)
        # On Linux: use your user ID
        # On macOS: Docker handles this automatically
        
        if [[ "$OSTYPE" == "linux-gnu"* ]]; then
            sudo chown -R $(id -u):$(id -g) backend/
        fi
        
        # Set directory permissions
        chmod -R 755 backend/
        
        # Set writable directories
        if [ -d "backend/storage" ]; then
            chmod -R 775 backend/storage
        fi
        
        if [ -d "backend/bootstrap/cache" ]; then
            chmod -R 775 backend/bootstrap/cache
        fi
        
        log_info "✓ Permissions set"
    fi
}

# Build and start containers
start_containers() {
    log_info "Building and starting Docker containers..."
    
    # Build images
    docker-compose build --no-cache
    
    # Start containers
    docker-compose up -d
    
    log_info "✓ Containers started"
}

# Wait for services to be healthy
wait_for_services() {
    log_info "Waiting for services to be ready..."
    
    # Wait for MySQL
    log_info "Waiting for MySQL..."
    until docker-compose exec -T mysql mysqladmin ping -h localhost --silent; do
        printf '.'
        sleep 2
    done
    echo ""
    log_info "✓ MySQL is ready"
    
    # Wait for Redis
    log_info "Waiting for Redis..."
    until docker-compose exec -T redis redis-cli ping | grep -q PONG; do
        printf '.'
        sleep 1
    done
    echo ""
    log_info "✓ Redis is ready"
    
    # Wait for Meilisearch
    log_info "Waiting for Meilisearch..."
    until curl -s http://localhost:7700/health | grep -q available; do
        printf '.'
        sleep 2
    done
    echo ""
    log_info "✓ Meilisearch is ready"
}

# Install Composer dependencies
install_dependencies() {
    log_info "Installing Composer dependencies..."
    
    docker-compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader
    
    log_info "✓ Dependencies installed"
}

# Run migrations
run_migrations() {
    log_info "Running database migrations..."
    
    docker-compose exec -T app php artisan migrate --force
    
    log_info "✓ Migrations completed"
}

# Display success message
display_success() {
    echo ""
    echo "=========================================================================="
    log_info "CI Insights Dashboard setup completed successfully!"
    echo "=========================================================================="
    echo ""
    echo "Services running:"
    echo "  - Application:  http://localhost:8080"
    echo "  - MySQL:        localhost:3307"
    echo "  - Redis:        localhost:6380"
    echo "  - Meilisearch:  http://localhost:7700"
    echo ""
    echo "Useful commands:"
    echo "  - View logs:         docker-compose logs -f"
    echo "  - Run artisan:       docker-compose exec app php artisan"
    echo "  - Run tests:         docker-compose exec app php artisan test"
    echo "  - Stop services:     docker-compose down"
    echo "  - Restart services:  docker-compose restart"
    echo ""
    echo "Next steps:"
    echo "  1. Configure GitHub OAuth credentials in backend/.env"
    echo "  2. Run 'docker-compose exec app php artisan key:generate' if not done"
    echo "  3. Review the ADR documentation in docs/adr/"
    echo ""
    echo "=========================================================================="
}

# Main execution flow
main() {
    echo "=========================================================================="
    echo "  CI Insights Dashboard - Setup Script"
    echo "=========================================================================="
    echo ""
    
    check_prerequisites
    create_project_structure
    create_laravel_project
    setup_environment
    update_env_config
    set_permissions
    start_containers
    wait_for_services
    install_dependencies
    generate_app_key
    run_migrations
    display_success
}

# Run main function
main "$@"