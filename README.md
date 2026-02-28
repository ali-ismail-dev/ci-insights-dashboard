# CI Insights Dashboard
Monitoring Test: [Current Time]
> **Production-grade Code Review & CI Analytics Platform**  
> Built with Laravel 11, React 18, and Docker

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php)](https://www.php.net)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?logo=docker)](https://www.docker.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## 📋 Overview

A comprehensive dashboard for engineering teams to gain visibility into:
- **PR Health Metrics:** Cycle time, review delays, merge frequency
- **Flaky Test Detection:** Identify unstable tests across CI runs
- **Test Coverage Trends:** Track coverage changes over time
- **Long-Running PRs:** Alert on stale pull requests
- **File-Level CI Failures:** Correlate files with CI failure rates
- **Engineering Metrics:** Recruiter-friendly productivity insights

**Tech Stack:**
- **Backend:** Laravel 11 (PHP 8.2) with strict architecture patterns
- **Frontend:** React 18 + TypeScript + Tailwind CSS
- **Database:** MySQL 8.0 with JSON support
- **Cache/Queue:** Redis 7 with Horizon monitoring
- **Search:** Meilisearch for instant search
- **Deployment:** Docker Compose (local) → Railway/Fly.io (production)

---

## 🚀 Quick Start

### Prerequisites

- Docker Desktop (4.x+) with 4GB RAM allocated
- Docker Compose (v3.8+)
- Git

### Installation (5 minutes)

```bash
# 1. Clone the repository
git clone <repository-url>
cd ci-insights-dashboard

# 2. Make setup script executable
chmod +x setup.sh

# 3. Run automated setup
./setup.sh

# 4. Access the application
open http://localhost:8080
```

The setup script will:
- ✅ Create Laravel project
- ✅ Build Docker images
- ✅ Start all services (Nginx, PHP, MySQL, Redis, Meilisearch)
- ✅ Install dependencies
- ✅ Run database migrations
- ✅ Configure environment variables

### Manual Setup (Alternative)

```bash
# Create project structure
mkdir -p backend docker/{nginx/conf.d,php,mysql/init}

# Copy configuration files (see docker/ directory)
# ...

# Start services
docker-compose up -d

# Install dependencies
docker-compose exec app composer install

# Generate application key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate
```

---

## 🏗️ Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                     GitHub Webhooks                         │
│                    (PR events, CI runs)                     │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                   Nginx (Port 8080)                         │
│              Rate Limiting & Load Balancing                 │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              Laravel Application (PHP-FPM)                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ Controllers  │  │   Services   │  │   Actions    │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└──────────┬────────────────┬────────────────┬───────────────┘
           │                │                │
           ▼                ▼                ▼
  ┌────────────────┐ ┌────────────┐ ┌──────────────┐
  │ MySQL 8.0      │ │ Redis 7    │ │ Meilisearch  │
  │ (Port 3307)    │ │ (Port 6380)│ │ (Port 7700)  │
  │                │ │            │ │              │
  │ • PR data      │ │ • Cache    │ │ • PR search  │
  │ • Test results │ │ • Sessions │ │ • File index │
  │ • Metrics      │ │ • Queue    │ │ • Facets     │
  └────────────────┘ └────────────┘ └──────────────┘
           ▲                ▲
           │                │
    ┌──────┴────────┐  ┌────┴───────┐
    │ Laravel       │  │ Laravel    │
    │ Horizon       │  │ Scheduler  │
    │ (Queue Worker)│  │ (Cron)     │
    └───────────────┘  └────────────┘
```

### Directory Structure

```
ci-insights-dashboard/
├── backend/                    # Laravel application
│   ├── app/
│   │   ├── Actions/           # Single-purpose business logic
│   │   ├── DTOs/              # Data Transfer Objects
│   │   ├── Services/          # Reusable service logic
│   │   ├── Http/
│   │   │   ├── Controllers/   # Thin controllers
│   │   │   ├── Requests/      # Form validation
│   │   │   └── Resources/     # JSON transformations
│   │   └── Models/            # Eloquent models
│   ├── database/
│   │   ├── migrations/        # Schema definitions
│   │   └── seeders/           # Test data
│   └── tests/
│       ├── Feature/           # HTTP/Integration tests
│       ├── Unit/              # Logic tests
│       └── Architecture/      # Pest architecture tests
├── frontend/                   # React application (coming in Step 2)
├── docker/
│   ├── nginx/                 # Nginx configuration
│   ├── php/                   # PHP-FPM configuration
│   └── mysql/                 # MySQL initialization
├── docs/
│   └── adr/                   # Architecture Decision Records
├── docker-compose.yml         # Service orchestration
├── setup.sh                   # Automated setup script
└── README.md                  # This file
```

---

## 🐳 Docker Services

| Service | Port | Purpose | Resources |
|---------|------|---------|-----------|
| **nginx** | 8080 | Web server & reverse proxy | 10-20MB RAM |
| **app** | 9000 | PHP-FPM application server | 100-200MB RAM |
| **mysql** | 3307 | Database (persistent storage) | 256-400MB RAM |
| **redis** | 6380 | Cache, sessions, queue | 20-50MB RAM |
| **meilisearch** | 7700 | Search engine | 50-150MB RAM |
| **horizon** | - | Queue worker monitoring | 50-150MB RAM |
| **scheduler** | - | Laravel cron jobs | 30-80MB RAM |

**Total RAM:** ~1.5GB (fits on 2GB free-tier instances)

---

## 🛠️ Common Commands

### Application Management

```bash
# View logs
docker-compose logs -f app

# Run artisan commands
docker-compose exec app php artisan {command}

# Access Laravel Tinker (REPL)
docker-compose exec app php artisan tinker

# Clear caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear

# Run tests
docker-compose exec app php artisan test
docker-compose exec app php artisan test --coverage

# Check Horizon status
docker-compose exec app php artisan horizon:status
```

### Database Management

```bash
# Run migrations
docker-compose exec app php artisan migrate

# Fresh migrations (WARNING: drops all tables)
docker-compose exec app php artisan migrate:fresh

# Rollback last migration
docker-compose exec app php artisan migrate:rollback

# Seed database with test data
docker-compose exec app php artisan db:seed

# Access MySQL CLI
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights

# Backup database
docker-compose exec mysql mysqldump -uci_user -pci_secure_password_change_in_prod ci_insights > backup.sql
```

### Queue Management

```bash
# View Horizon dashboard
open http://localhost:8080/horizon

# Process queue manually
docker-compose exec app php artisan queue:work

# Retry failed jobs
docker-compose exec app php artisan queue:retry all

# Clear failed jobs
docker-compose exec app php artisan queue:flush
```

### Search Management

The backend makes use of **Laravel Scout** to provide full‑text search over
repositories and pull requests.  By default the docker configuration sets
`SCOUT_DRIVER=meilisearch` and a Meilisearch container is available on port 7700.

If you prefer Elasticsearch you can swap the driver:

```bash
# install official or community scout driver for elastic
composer require babenkoivan/scout-elasticsearch-driver
# then point driver to elastic in .env
SCOUT_DRIVER=elasticsearch
SCOUT_ELASTICSEARCH_HOST=http://elasticsearch:9200
```

Models marked with the `Searchable` trait (`Repository`, `PullRequest`, etc.)
are automatically indexed when created/updated.  You can rebuild or clean the
index using the Artisan commands below:

```bash
# Import models to the configured engine
docker-compose exec app php artisan scout:import "App\Models\PullRequest"

# Flush search index
docker-compose exec app php artisan scout:flush "App\Models\PullRequest"

# Access Meilisearch (default)
open http://localhost:7700
```

The UI exposes a global search icon in the top header (visible on every page).
Clicking it opens a modal where users can type a query; results are fetched
from `GET /api/search` and include matching repositories and pull requests.
This is intended as an application‑wide lookup rather than a page‑specific
filter.  Repository list pages still support scoped filtering via the
`search` query parameter.

### Container Management

```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# Restart specific service
docker-compose restart app

# View container stats
docker stats

# Rebuild containers
docker-compose build --no-cache

# Remove all containers and volumes (DESTRUCTIVE)
docker-compose down -v
```

---

## 🧪 Testing

### Test Structure

```
tests/
├── Feature/           # HTTP endpoints, database interactions
│   ├── WebhookTest.php
│   ├── PullRequestTest.php
│   └── SearchTest.php
├── Unit/              # Pure logic, no external dependencies
│   ├── Actions/
│   ├── Services/
│   └── DTOs/
└── Architecture/      # Enforce code standards
    └── ArchTest.php   # Controllers don't query DB directly, etc.
```

### Running Tests

```bash
# Run all tests
docker-compose exec app php artisan test

# Run specific test file
docker-compose exec app php artisan test --filter=WebhookTest

# Run with coverage
docker-compose exec app php artisan test --coverage

# Run architecture tests
docker-compose exec app php artisan test --filter=ArchTest

# Parallel testing (faster)
docker-compose exec app php artisan test --parallel
```

### Code Quality

```bash
# Static analysis (Larastan)
docker-compose exec app ./vendor/bin/phpstan analyse

# Code formatting (Laravel Pint)
docker-compose exec app ./vendor/bin/pint

# Test coverage report
docker-compose exec app php artisan test --coverage-html=coverage
open backend/coverage/index.html
```

---

## 📊 Monitoring & Health Checks

### Health Endpoints

```bash
# Application health
curl http://localhost:8080/health
# Expected: "healthy"

# Horizon status
curl http://localhost:8080/horizon/api/stats
# Expected: JSON with queue stats

# Meilisearch health
curl http://localhost:7700/health
# Expected: {"status":"available"}
```

### Logs

```bash
# Application logs
docker-compose logs -f app

# Nginx access logs
docker-compose logs -f nginx

# MySQL error logs
docker-compose exec mysql tail -f /var/log/mysql/error.log

# Redis logs
docker-compose logs -f redis
```

### Metrics

Access Laravel Telescope (development only):
```
http://localhost:8080/telescope
```

---

## 🔒 Security

### Environment Variables

**Never commit `.env` file to Git!**

Required environment variables:
```env
APP_KEY=                        # Generate with: php artisan key:generate
DB_PASSWORD=                    # Strong password (20+ chars)
REDIS_PASSWORD=                 # Optional, but recommended in production
GITHUB_WEBHOOK_SECRET=          # For webhook signature verification
GITHUB_CLIENT_ID=               # For OAuth
GITHUB_CLIENT_SECRET=           # For OAuth
MEILISEARCH_KEY=               # Change in production
```

### Rate Limiting

| Endpoint | Limit | Burst |
|----------|-------|-------|
| Global | 100 req/sec | 20 |
| API | 60 req/min | 10 |
| Webhooks | 30 req/min | 5 |

### HTTPS in Production

```nginx
# Nginx config (production)
server {
    listen 443 ssl http2;
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    # ... rest of config
}
```

---

## 🚢 Deployment

### Railway (Recommended for MVP)

```bash
# Install Railway CLI
npm i -g @railway/cli

# Login
railway login

# Create project
railway init

# Deploy
railway up
```

### Fly.io

```bash
# Install Fly CLI
curl -L https://fly.io/install.sh | sh

# Login
fly auth login

# Launch app
fly launch

# Deploy
fly deploy
```

### Docker Registry

```bash
# Build production image
docker build -t ci-insights:latest -f backend/Dockerfile --target production .

# Push to registry
docker tag ci-insights:latest registry.example.com/ci-insights:latest
docker push registry.example.com/ci-insights:latest
```

---

## 📚 Documentation

- **[Architecture Decision Records](docs/adr/):** Why we made specific technology choices
- **[Testing Guide](STEP_1_TESTING.md):** Comprehensive testing procedures
- **[API Documentation](http://localhost:8080/docs/api):** Auto-generated from OpenAPI annotations
- **[Database Schema](docs/schema.md):** Coming in Step 2

---

## 🤝 Contributing

1. **Fork the repository**
2. **Create a feature branch:** `git checkout -b feature/amazing-feature`
3. **Write tests:** `docker-compose exec app php artisan test`
4. **Run static analysis:** `docker-compose exec app ./vendor/bin/phpstan analyse`
5. **Commit changes:** `git commit -m 'Add amazing feature'`
6. **Push to branch:** `git push origin feature/amazing-feature`
7. **Open Pull Request**

### Code Standards

- PSR-12 coding style (enforced by Laravel Pint)
- 100% test coverage on critical paths
- PHPDoc on all public methods
- Architecture tests must pass
- No direct DB queries in controllers

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- Built with [Laravel](https://laravel.com) - The PHP Framework for Web Artisans
- Search powered by [Meilisearch](https://www.meilisearch.com/)
- Queue monitoring by [Laravel Horizon](https://laravel.com/docs/11.x/horizon)

---

## 📞 Support

- **Issues:** [GitHub Issues](https://github.com/your-repo/issues)
- **Discussions:** [GitHub Discussions](https://github.com/your-repo/discussions)
- **Email:** support@example.com

---

**Current Status:** ✅ Step 1 Complete (Docker Setup)  
**Next Steps:** Database schema design, webhook processing, background jobs

**Built with ❤️ for engineering teams**
