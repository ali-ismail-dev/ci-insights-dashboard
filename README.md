# CI Insights Dashboard

> **Production-grade Code Review & CI Analytics Platform**  
> An observability hub that offloads heavy CI artifact processing to asynchronous queues, delivering real-time GitHub metrics and regression analysis without UI thread contention.

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php)](https://www.php.net)
[![React](https://img.shields.io/badge/React-18-61DAFB?logo=react)](https://react.dev)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?logo=docker)](https://www.docker.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## 📋 Executive Overview

A centralized intelligence hub built for engineering teams to gain real-time visibility into their development pipelines. The platform solves the problem of CI data fragmentation by aggregating:
- **PR Health Metrics:** Cycle time, review delays, and merge frequency.
- **Flaky Test Detection:** Identifying unstable tests across massive CI runs.
- **Code Coverage Trends:** Tracking file-level test coverage degradation.

By leveraging webhook listeners, Redis-backed queues, and Meilisearch, the system processes heavy JSON payloads asynchronously, ensuring the React frontend remains lightning-fast even during peak deployment hours.

---

## 🏗️ System Topology

```text
┌─────────────────────────────────────────────────────────────┐
│                     GitHub Webhooks                         │
│                   (PR events, CI runs)                      │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                    Nginx (Port 8080)                        │
│              Rate Limiting & Load Balancing                 │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              Laravel Application (PHP-FPM)                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │ Controllers  │  │   Services   │  │   Actions    │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
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
🚀 Core Architectural Decisions
1. Asynchronous Webhook Processing (Redis & Horizon)
Processing large-scale test artifacts and coverage reports synchronously blocks web servers and causes webhook timeouts. This architecture immediately acknowledges GitHub webhooks and offloads the heavy JSON parsing to Redis-backed queue workers monitored by Laravel Horizon.

2. High-Performance Instant Search (Meilisearch)
Replaced expensive LIKE %...% SQL queries with Meilisearch via Laravel Scout. Models like PullRequest and Repository are automatically indexed, allowing engineers to execute sub-millisecond structural text queries and filter out "flaky" files instantly.

3. Decoupled Service-Action Architecture
The Laravel backend strictly adheres to a thin-controller pattern. Business logic is isolated into single-purpose Actions (e.g., ProcessCIArtifactAction), while external data fetching is handled by Services, ensuring the codebase remains highly testable and modular.

```Bash
#1. Clone the repository
git clone [https://github.com/ali-ismail-dev/ci-insights-dashboard.git](https://github.com/ali-ismail-dev/ci-insights-dashboard.git)
cd ci-insights-dashboard

# 2. Make setup script executable
chmod +x setup.sh

# 3. Run automated setup
./setup.sh

# 4. Access the application
# Frontend Dashboard: http://localhost:8080
# Horizon Dashboard: http://localhost:8080/horizon
```
Search Engine Management
Models marked with the Searchable trait are automatically indexed. To manually flush or rebuild the Meilisearch indexes:
```Bash
# Import models to the configured engine
docker-compose exec app php artisan scout:import "App\Models\PullRequest"

# Flush search index
docker-compose exec app php artisan scout:flush "App\Models\PullRequest"
```
🧪 Testing & Quality Assurance
The backend maintains strict reliability through comprehensive test suites, leveraging Pest/PHPUnit for integration testing and Larastan for static analysis.
```Bash
# Run all integration & unit tests
docker-compose exec app php artisan test

# Run strict architecture tests (enforcing pattern compliance)
docker-compose exec app php artisan test --filter=ArchTest

# Static analysis (Larastan)
docker-compose exec app ./vendor/bin/phpstan analyse
```
🚢 Deployment Strategy
The system is optimized for automated containerized deployments using modern PaaS providers.
Fly.io Deployment
```Bash
curl -L [https://fly.io/install.sh](https://fly.io/install.sh) | sh
fly auth login
fly launch
fly deploy
```
Production Security & Limitations
Rate Limiting: Global limits set to 100 req/sec; Webhooks restricted to 30 req/min to prevent DDoS via payload injection.
Env Security: Secrets (GITHUB_WEBHOOK_SECRET, MEILISEARCH_KEY) injected securely via PaaS environment variables, never committed to version control.
Architected and maintained by Ali Ismail.
