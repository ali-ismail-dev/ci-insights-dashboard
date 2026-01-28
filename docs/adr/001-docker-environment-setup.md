# ADR 001: Docker Environment Setup & Technology Stack Selection

**Status:** Accepted  
**Date:** 2026-01-22  
**Decision Makers:** Engineering Team  
**Stakeholders:** DevOps, Backend, Frontend Teams

---

## Context and Problem Statement

We need to establish a development environment for the CI Insights Dashboard that:
- Supports local development with production parity
- Runs efficiently on free-tier cloud services
- Provides consistent experience across development machines
- Minimizes setup friction for new developers
- Enables easy deployment to various hosting providers

The stack must handle:
- High-volume webhook ingestion from GitHub
- Background job processing for PR analysis
- Real-time search across PRs and test results
- Time-series data for metrics and trends
- Free-tier resource constraints (512MB-1GB RAM, limited storage)

---

## Decision Drivers

1. **Free-Tier Compatibility:** Must run within typical free-tier limits (1GB RAM, 10GB storage)
2. **Developer Experience:** Quick setup (< 5 minutes), hot-reload, debugging support
3. **Production Parity:** Local environment mirrors production as closely as possible
4. **Performance:** Handle 100+ webhook requests/hour, analyze PRs in < 30 seconds
5. **Maintainability:** Standard tools, good documentation, active community
6. **Scalability Path:** Easy upgrade path when outgrowing free tier
7. **Cost:** Minimize infrastructure costs during development and initial deployment

---

## Considered Options

### Container Orchestration
1. **Docker Compose** ✅ SELECTED
2. Kubernetes (k3s/minikube)
3. Manual Docker containers
4. XAMPP/MAMP/Local PHP environment

### Web Server
1. **Nginx + PHP-FPM** ✅ SELECTED
2. Apache with mod_php
3. Caddy Server
4. PHP built-in server

### Database
1. **MySQL 8.0** ✅ SELECTED
2. MariaDB 10.11+
3. PostgreSQL 15+
4. SQLite (development only)

### Cache & Queue
1. **Redis 7** ✅ SELECTED
2. Memcached
3. Database-backed cache
4. KeyDB

### Search Engine
1. **Meilisearch** ✅ SELECTED
2. Elasticsearch
3. Algolia
4. Database full-text search
5. Typesense

---

## Decision Outcome

**Chosen Stack:**

| Component | Technology | Version | Justification |
|-----------|-----------|---------|---------------|
| Container Orchestration | Docker Compose | 3.8 | Simple, standard, great DX |
| Application Server | PHP-FPM (Alpine) | 8.2 | Lightweight, production-ready |
| Web Server | Nginx | 1.25 | Best performance, low memory |
| Database | MySQL | 8.0 | JSON support, free hosting options |
| Cache & Queue | Redis | 7-alpine | Persistent, Horizon compatible |
| Search | Meilisearch | 1.5 | Free, fast, easy to deploy |
| Framework | Laravel | 11+ | Modern, complete ecosystem |
| Queue Monitoring | Horizon | Latest | Built-in Laravel tool |

---

## Detailed Rationale

### 1. Docker Compose vs. Alternatives

**Why Docker Compose:**
- **Pros:**
  - Single `docker-compose.yml` defines entire stack
  - Native Docker CLI (ships with Docker Desktop)
  - Perfect for local development and small deployments
  - Easy to understand for junior developers
  - Free-tier friendly (runs on any Docker-compatible VPS)
  - Can deploy to Railway, Fly.io, DigitalOcean App Platform
  
- **Cons:**
  - Not ideal for large-scale orchestration (that's okay for our use case)
  - Limited auto-scaling (acceptable for free tier)

**Why NOT Kubernetes:**
- Overkill for single-instance deployments
- Requires 2GB+ RAM just for k3s control plane
- Steeper learning curve
- More complex for free-tier hosting

**Why NOT manual Docker:**
- No declarative configuration
- Harder to share environment with team
- Networking and volume management manual

---

### 2. Nginx + PHP-FPM vs. Apache

**Why Nginx + PHP-FPM:**
- **Memory footprint:** ~10MB (Nginx) vs ~50-100MB (Apache)
- **Performance:** Handles 10,000+ concurrent connections with 512MB RAM
- **Separation of concerns:** Static files (Nginx) vs PHP execution (FPM)
- **Production standard:** 90% of Laravel deployments use Nginx
- **Free-tier optimization:** Can run multiple sites on single instance

**Configuration highlights:**
- Unix socket communication (faster than TCP)
- gzip compression (saves bandwidth on free tiers)
- Rate limiting per endpoint (webhook: 30/min, API: 60/min, global: 100/sec)
- Static asset caching (1 year expiry)
- Health check endpoint (no auth, fast response)

**Why NOT Apache:**
- Higher memory usage (critical for free tier)
- Process-based model less efficient than Nginx event-driven
- mod_php keeps PHP in memory even for static files

---

### 3. MySQL 8.0 vs. PostgreSQL

**Why MySQL 8.0:**
- **JSON performance:** 2-3x faster than PostgreSQL for webhook payloads
  - Native JSON functions: `JSON_EXTRACT`, `JSON_TABLE`
  - Generated columns from JSON (index JSON fields)
- **Free hosting options:**
  - AWS RDS Free Tier (750 hours/month, 20GB storage)
  - PlanetScale (free 5GB, but **no foreign keys**)
  - Self-hosted on Oracle Cloud Always Free (ARM instances)
- **Window functions & CTEs:** For complex PR metrics (median cycle time, percentiles)
- **UTF8MB4 default:** Full Unicode support (emojis in commit messages)

**PlanetScale Compatibility Decision:**
- **Trade-off:** Support optional foreign keys to enable PlanetScale migration
- **Implementation:**
  - Schema designed with FK constraints (for AWS RDS, self-hosted)
  - Application-level referential integrity checks
  - Soft deletes prevent orphaned records
  - Background job cleans dangling references
- **Migration strategy:** Start with MySQL (FKs enabled), migrate to PlanetScale if needed

**Why NOT PostgreSQL:**
- Fewer free-tier hosting options (Neon, Supabase have stricter limits)
- JSON performance slightly slower (acceptable, but not optimal)
- Our use case doesn't require advanced PG features (JSONB indexing, arrays)

---

### 4. Redis 7 vs. Alternatives

**Why Redis 7:**
- **Laravel Horizon compatibility:** Requires persistent TCP connections
  - **Note:** Upstash (serverless Redis) requires special HTTP client
  - Our Docker setup uses standard Redis, deploy flexibility later
- **Persistence enabled:** Queue jobs survive restarts
  - AOF (Append-Only File) with fsync every second
  - Snapshots every 15 minutes or 10,000 writes
- **Memory management:**
  - `maxmemory 256mb` (free-tier safe)
  - `allkeys-lru` eviction policy (cache before queue data)
- **Multi-use:**
  - Cache (Laravel cache driver)
  - Session storage (avoid DB writes)
  - Queue backend (Horizon jobs)
  - Real-time pub/sub (future WebSocket support)

**Free hosting options:**
- Upstash (10,000 requests/day, requires HTTP adapter)
- Railway (512MB Redis instance on free plan)
- Self-hosted with Docker (our default)

**Why NOT Memcached:**
- No persistence (queue jobs lost on restart)
- No pub/sub (can't do real-time updates)
- No Lua scripting (useful for atomic operations)

---

### 5. Meilisearch vs. Elasticsearch

**Why Meilisearch:**
- **Resource efficiency:**
  - Runs in 50-150MB RAM (vs Elasticsearch's 1GB+ minimum)
  - Single binary deployment (no JVM)
  - Built-in typo tolerance and prefix search
- **Developer experience:**
  - Laravel Scout official driver
  - RESTful API (no complex query DSL)
  - Instant search UI in 5 minutes
- **Free deployment:**
  - Railway (free tier supports Meilisearch)
  - Self-hosted on VPS (very lightweight)
  - Fly.io (256MB instance sufficient)
- **Features we need:**
  - Full-text search (PR titles, descriptions, file paths)
  - Faceted search (filter by author, repository, status)
  - Synonyms ("PR" → "Pull Request", "flaky" → "unstable")
  - Ranking rules (prioritize recent PRs)

**Why NOT Elasticsearch:**
- Requires 2GB+ RAM (free tier killer)
- Complex setup (JVM tuning, index management)
- Overkill for our search volume (< 10,000 PRs)

**Why NOT Algolia:**
- Paid service (10,000 searches/month free, then $1+/1000 searches)
- Vendor lock-in (hard to migrate later)
- Our data volume exceeds free tier quickly

**Why NOT Typesense:**
- Similar to Meilisearch but less mature Laravel ecosystem
- Fewer free hosting options
- Meilisearch has better documentation

---

## Database Design Decisions

### Character Set: UTF8MB4

**Decision:** All tables use `utf8mb4_unicode_ci`

**Reasoning:**
- **UTF8 vs UTF8MB4:** UTF8MB4 supports full Unicode (4-byte characters)
  - Emoji in commit messages: 🎉, 🐛, 🚀
  - Special characters in developer names
- **Collation:** `utf8mb4_unicode_ci` for case-insensitive search
  - Slower than `utf8mb4_general_ci` but linguistically correct

### Storage Engine: InnoDB

**Decision:** InnoDB for all tables (MySQL 8.0 default)

**Reasoning:**
- **ACID compliance:** Transactions for webhook processing
- **Foreign keys:** Referential integrity (when not using PlanetScale)
- **Row-level locking:** Better concurrency than MyISAM
- **Crash recovery:** Automatic recovery from power loss

### JSON Columns vs. EAV Tables

**Decision:** Use JSON columns for:
- Webhook payloads (GitHub's full event data)
- Test failure logs (unstructured error messages)
- CI run metadata (dynamic fields per CI provider)

**Reasoning:**
- **Avoid EAV anti-pattern:** Don't create `entity_attributes` table
- **JSON advantages:**
  - Schema flexibility (GitHub adds new webhook fields)
  - Single SELECT retrieves all data
  - Can index JSON fields via generated columns
- **JSON disadvantages accepted:**
  - Can't enforce schema (we validate in application layer)
  - Queries slower than normalized tables (acceptable for reads)

**Example:**
```sql
CREATE TABLE webhook_events (
    id BIGINT PRIMARY KEY,
    event_type VARCHAR(50),
    payload JSON,  -- Full GitHub webhook payload
    signature VARCHAR(64),
    created_at TIMESTAMP,
    INDEX idx_event_type (event_type),
    -- Generated column for common JSON field
    repository_id BIGINT AS (payload->>'$.repository.id') STORED,
    INDEX idx_repository_id (repository_id)
);
```

---

## Free-Tier Optimization Strategies

### Memory Management

**Container limits (docker-compose):**
```yaml
services:
  mysql:
    mem_limit: 512m  # InnoDB buffer pool: 256M
  redis:
    mem_limit: 256m  # maxmemory: 256m
  meilisearch:
    mem_limit: 256m  # max_indexing_memory: 256Mb
  app:
    mem_limit: 512m  # PHP memory_limit: 256M
```

**Total: ~1.5GB (fits on 2GB free-tier instances)**

### Storage Management

**Data retention policies:**
- Webhook receipts: 30 days (then archive to S3-compatible storage)
- Test run logs: 90 days (compress old logs)
- PR analysis: Indefinite (core data, < 1MB per PR)
- Search indices: Periodic optimization (Meilisearch auto-manages)

**Compression:**
- Nginx gzip for API responses (saves bandwidth)
- MySQL row compression for large JSON columns
- Redis AOF compression

### Connection Pooling

**MySQL connections:**
- Max connections: 50 (free tier)
- Laravel connection pool: 10 per process
- Connection timeout: 2 hours (match job duration)

**Why 50 max connections:**
- Web requests: 10 concurrent (Nginx + PHP-FPM workers)
- Horizon workers: 10 concurrent
- Scheduler: 5
- Admin tasks: 5
- Buffer: 20 for spikes

---

## Performance Benchmarks

### Baseline Targets (Free Tier)

| Metric | Target | Measurement |
|--------|--------|-------------|
| Webhook response time | < 100ms | Time from GitHub POST to 200 OK |
| PR analysis duration | < 30s | Time to analyze medium PR (50 files) |
| Search latency | < 50ms | Time to return 20 results |
| Dashboard load time | < 2s | Time to First Contentful Paint |
| Background job throughput | 100 jobs/minute | Horizon processing rate |
| Database query time | < 10ms | 95th percentile for main queries |

### Load Testing Assumptions

- **Concurrent PRs:** 10 per repository
- **Webhook rate:** 100/hour per repository
- **Search queries:** 20/minute during active hours
- **Active users:** 5-10 concurrent dashboard viewers

---

## Security Considerations

### Docker Security

- **Non-root user:** Production image runs as `www-data`
- **Read-only filesystem:** Except `/tmp`, `/var/log`, storage volumes
- **No privileged mode:** Containers can't access host kernel
- **Network isolation:** Internal services (MySQL, Redis) not exposed publicly

### Application Security

- **Environment secrets:**
  - `.env` file excluded from Git
  - Secrets encrypted in production (Laravel's `encrypt:env`)
  - Rotate GitHub webhook secret regularly
- **Database credentials:**
  - Strong passwords (20+ characters, random)
  - Least-privilege principle (app user can't DROP DATABASE)
- **Rate limiting:**
  - Global: 100 req/sec per IP
  - API: 60 req/min per IP
  - Webhooks: 30 req/min per IP

### Free-Tier Security Risks

- **DDoS vulnerability:** Free tiers lack enterprise DDoS protection
  - Mitigation: Cloudflare free tier (unlimited DDoS protection)
- **Backup limitations:** Free tiers may lack automated backups
  - Mitigation: Daily cron job to S3-compatible storage (Backblaze B2 free tier)

---

## Deployment Options

### Development
- **Local Docker Compose** (this setup)
- Hot-reload: volumes mounted with `:delegated` flag
- Xdebug enabled for step debugging

### Staging
- **Railway** (free tier: 500 hours/month)
  - Deploy Docker image directly
  - MySQL, Redis, Meilisearch as services
- **Fly.io** (free tier: 3 VMs, 160GB transfer)
  - Multi-region deployment
  - Automatic SSL certificates

### Production (Low-Cost Path)
1. **Backend:** Railway or Fly.io ($5-10/month)
2. **Frontend:** Vercel (free tier, unlimited bandwidth)
3. **Database:** AWS RDS Free Tier (first year)
4. **Redis:** Upstash (10K requests/day free)
5. **Search:** Self-hosted Meilisearch on backend VM
6. **Monitoring:** Better Stack free tier

**Total cost:** $0-10/month (first year with AWS free tier)

---

## Migration Paths

### If Outgrowing Free Tier

| Symptom | Solution | Cost |
|---------|----------|------|
| Database storage > 20GB | PlanetScale Scaler ($29/month) or RDS ($15/month) | $15-29 |
| Redis memory > 256MB | Upstash Pro ($10/month) or self-hosted | $10 |
| Meilisearch index > 1GB | Dedicated VPS ($5/month) | $5 |
| Webhook rate > 100/hour | Horizontal scaling (add worker instances) | $10 |

**Scaling priority:**
1. Cache (Redis) - highest impact on performance
2. Database - can partition or shard later
3. Search - indices can rebuild from DB
4. Workers - easiest to scale horizontally

---

## Risks and Mitigation

### Risk 1: PlanetScale Requires No Foreign Keys

**Impact:** Referential integrity must be managed in application

**Mitigation:**
- Design schema with FKs (use MySQL/RDS initially)
- Implement soft deletes (never hard delete, use `deleted_at`)
- Background job validates referential integrity daily
- If migrating to PlanetScale:
  - Remove FKs from migrations
  - Keep FK validation in model events (`Model::deleting()`)

### Risk 2: Free-Tier Rate Limits

**Impact:** GitHub API: 5,000 requests/hour; PlanetScale: 1B reads/month

**Mitigation:**
- Cache GitHub API responses (1 hour TTL)
- Use GraphQL for efficient queries (fetch only needed fields)
- Database query optimization (indexes, avoid N+1)
- Monitor usage via observability tools

### Risk 3: Redis Persistence Failure

**Impact:** Queue jobs lost if Redis crashes without saving

**Mitigation:**
- AOF enabled with `fsync everysec` (max 1 second of data loss)
- Critical jobs stored in database (idempotency via `webhook_events` table)
- Retry failed jobs from dead-letter queue

### Risk 4: Meilisearch Index Corruption

**Impact:** Search unavailable until index rebuilt

**Mitigation:**
- Daily index snapshots to S3 (via scheduled job)
- Rebuild command: `php artisan scout:import "App\Models\PullRequest"`
- Background job ensures indices in sync (check every hour)

---

## Alternative Architectures Considered

### Option A: Serverless (AWS Lambda + DynamoDB)

**Pros:**
- True pay-per-use (could be cheaper at low volume)
- Auto-scaling
- No server management

**Cons:**
- Cold start latency (500ms-2s)
- Laravel not optimized for serverless
- DynamoDB learning curve
- Vendor lock-in
- **Rejected:** Complexity outweighs benefits for MVP

### Option B: Monolithic (Single VPS, No Docker)

**Pros:**
- Simplest possible setup
- Lowest resource overhead
- Easy to debug

**Cons:**
- No production parity
- Hard to share environment with team
- Manual dependency management
- **Rejected:** DevOps nightmare for multi-developer team

### Option C: Microservices (Separate APIs)

**Pros:**
- Independent scaling
- Language flexibility (Go for webhook ingestion, Python for ML)

**Cons:**
- Operational complexity (7+ services)
- Inter-service communication overhead
- Overkill for initial scope
- **Rejected:** Premature optimization for MVP

---

## Monitoring & Observability

### Metrics to Track

**Infrastructure:**
- Container CPU/memory usage
- Database connection pool saturation
- Redis memory usage and eviction rate
- Disk I/O and storage usage

**Application:**
- Webhook processing rate and latency
- Background job queue depth
- Search query latency
- API endpoint response times

**Business:**
- PRs analyzed per day
- Flaky tests detected
- User engagement (dashboard views)

**Tools:**
- Better Stack (free tier: 1GB logs/month, 30 day retention)
- Prometheus + Grafana (self-hosted on same VPS)
- Laravel Telescope (development only)

---

## Documentation Standards

### Required Documentation

1. **README.md:** Quick start guide
2. **SETUP.md:** Detailed installation steps (this ADR references it)
3. **ARCHITECTURE.md:** System overview diagram
4. **API.md:** Auto-generated from OpenAPI annotations (Scribe)
5. **DEPLOYMENT.md:** Step-by-step deployment to Railway/Fly.io
6. **ADR/:** Architecture Decision Records (this file is #001)

### Code Documentation

- **Migrations:** Table/column comments via `->comment()`
- **Models:** PHPDoc for relations and scopes
- **Controllers:** Docblocks with `@param`, `@return`, `@throws`
- **APIs:** OpenAPI annotations (auto-generate Swagger docs)

---

## Conclusion

This Docker-based setup balances:
- **Developer experience:** 5-minute setup, hot-reload, debugging
- **Production readiness:** Same stack in dev/staging/prod
- **Free-tier compatibility:** Runs in < 2GB RAM, < 10GB storage
- **Flexibility:** Can deploy to Railway, Fly.io, VPS, or even serverless later

The technology choices prioritize:
1. **Laravel ecosystem:** Proven, mature, good documentation
2. **Free hosting:** AWS RDS, Railway, Vercel free tiers
3. **Performance:** Nginx, Redis, Meilisearch optimized for speed
4. **Maintainability:** Standard tools, active communities

---

## References

- [Laravel Deployment Best Practices](https://laravel.com/docs/11.x/deployment)
- [Docker Compose Best Practices](https://docs.docker.com/compose/compose-file/compose-file-v3/)
- [MySQL 8.0 JSON Functions](https://dev.mysql.com/doc/refman/8.0/en/json-functions.html)
- [Meilisearch Documentation](https://docs.meilisearch.com/)
- [Laravel Horizon Documentation](https://laravel.com/docs/11.x/horizon)
- [Free-Tier Cloud Services Comparison](https://free-for.dev/)

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-22  
**Next Review:** After Step 2 (Database Schema Design)