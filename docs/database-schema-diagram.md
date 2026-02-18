# Database Schema Diagram

## Entity Relationship Diagram

```mermaid
erDiagram
    repositories ||--o{ pull_requests : "has many"
    repositories ||--o{ webhook_events : "receives"
    repositories ||--o{ test_runs : "has"
    repositories ||--o{ alert_rules : "configures"
    repositories ||--o{ daily_metrics : "aggregates"
    
    users ||--o{ pull_requests : "authors"
    users ||--o{ alert_rules : "creates"
    users ||--o{ alerts : "acknowledges"
    
    pull_requests ||--o{ test_runs : "triggers"
    pull_requests ||--o{ file_changes : "modifies"
    pull_requests ||--o{ alerts : "relates to"
    
    test_runs ||--o{ test_results : "contains"
    
    alert_rules ||--o{ alerts : "triggers"
    
    repositories {
        bigint id PK
        bigint external_id UK "GitHub repo ID"
        string full_name "owner/repo"
        string provider "github, gitlab"
        string webhook_secret "HMAC secret"
        boolean ci_enabled
        timestamp deleted_at "soft delete"
    }
    
    users {
        bigint id PK
        bigint external_id UK "GitHub user ID"
        string username UK
        string email UK
        string oauth_token "encrypted"
        string role "admin, member, viewer"
        timestamp last_login_at
        timestamp deleted_at "soft delete"
    }
    
    pull_requests {
        bigint id PK
        bigint repository_id FK
        bigint author_id FK
        bigint external_id "GitHub PR ID"
        int number "PR #"
        string state "open, merged, closed"
        string title
        string ci_status "success, failure"
        int cycle_time "seconds"
        int time_to_first_review
        decimal test_coverage "percentage"
        boolean is_stale
        timestamp merged_at
        timestamp deleted_at "soft delete"
    }
    
    webhook_events {
        bigint id PK
        bigint repository_id FK
        string delivery_id UK "idempotency key"
        string event_type "pull_request, push"
        string signature "HMAC"
        json payload "full webhook"
        string status "pending, completed"
        timestamp processed_at
    }
    
    test_runs {
        bigint id PK
        bigint repository_id FK
        bigint pull_request_id FK
        string ci_provider "github_actions, etc"
        string external_id "workflow_run_id"
        string status "success, failure"
        int total_tests
        int passed_tests
        int failed_tests
        int flaky_tests
        decimal line_coverage
        json failed_tests_details
        timestamp started_at
    }
    
    test_results {
        bigint id PK
        bigint test_run_id FK
        bigint repository_id FK
        string test_identifier UK "file::class::method"
        string status "passed, failed"
        boolean is_flaky
        decimal flakiness_score "0-100"
        text error_message
        timestamp executed_at
    }
    
    file_changes {
        bigint id PK
        bigint repository_id FK
        bigint pull_request_id FK
        string file_path
        string change_type "added, modified, deleted"
        int additions
        int deletions
        boolean caused_ci_failure
        decimal failure_rate "historical"
    }
    
    alert_rules {
        bigint id PK
        bigint repository_id FK
        bigint created_by_user_id FK
        string rule_type "flaky_test, stale_pr"
        json conditions "threshold, timeframe"
        string severity "low, medium, high"
        json notification_channels
        boolean is_active
        timestamp deleted_at "soft delete"
    }
    
    alerts {
        bigint id PK
        bigint alert_rule_id FK
        bigint repository_id FK
        bigint pull_request_id FK
        string alert_type
        string status "open, acknowledged, resolved"
        json context "what triggered"
        string fingerprint "deduplication"
        timestamp acknowledged_at
        timestamp resolved_at
    }
    
    daily_metrics {
        bigint id PK
        bigint repository_id FK
        date metric_date UK "YYYY-MM-DD"
        int prs_opened
        int prs_merged
        decimal avg_cycle_time
        decimal ci_success_rate
        decimal avg_line_coverage
        int flaky_tests_detected
        boolean is_final
    }
```

## Table Sizes & Growth

```mermaid
graph TD
    A[repositories<br/>~100 rows<br/>~100 KB] --> B[pull_requests<br/>~100K rows<br/>~200 MB]
    A --> C[webhook_events<br/>~30K rows/30 days<br/>~300 MB]
    A --> D[test_runs<br/>~270K rows/90 days<br/>~1.35 GB]
    A --> E[daily_metrics<br/>~10K rows<br/>~10 MB]
    
    B --> F[file_changes<br/>~500K rows<br/>~250 MB]
    B --> G[test_runs]
    
    D --> H[test_results<br/>~13.5M rows/90 days<br/>~6.75 GB]
    
    style A fill:#e1f5e1
    style B fill:#fff4e6
    style D fill:#ffe6e6
    style H fill:#ffe6e6
```

## Query Flow Examples

### Dashboard Overview Query

```mermaid
sequenceDiagram
    participant Dashboard
    participant daily_metrics
    participant repositories
    
    Dashboard->>daily_metrics: SELECT * WHERE repository_id=1 AND metric_date >= NOW()-30
    Note over daily_metrics: Uses idx_dailymetrics_repo_date<br/>Returns 30 rows in ~10ms
    daily_metrics-->>Dashboard: Aggregated metrics
    
    Dashboard->>repositories: SELECT * WHERE id=1
    repositories-->>Dashboard: Repository details
    
    Note over Dashboard: Render charts with 30 days of data<br/>Total query time: ~15ms
```

### Webhook Processing Flow

```mermaid
sequenceDiagram
    participant GitHub
    participant Nginx
    participant Laravel
    participant webhook_events
    participant Redis Queue
    participant Worker
    
    GitHub->>Nginx: POST /webhooks/github
    Nginx->>Laravel: Forward with signature
    Laravel->>webhook_events: INSERT (delivery_id, payload)
    Note over webhook_events: Idempotency check via UK
    webhook_events-->>Laravel: Event stored
    Laravel->>Redis Queue: Dispatch ProcessWebhookJob
    Laravel-->>GitHub: 202 Accepted
    
    Worker->>Redis Queue: Pop job
    Worker->>webhook_events: UPDATE status='processing'
    Worker->>pull_requests: INSERT/UPDATE PR data
    Worker->>webhook_events: UPDATE status='completed'
```

### Flaky Test Detection Query

```mermaid
sequenceDiagram
    participant Scheduler
    participant test_results
    participant alerts
    participant Slack
    
    Scheduler->>test_results: Analyze last 30 runs per test
    Note over test_results: SELECT test_identifier, COUNT(*), SUM(CASE WHEN status='failed')<br/>GROUP BY test_identifier HAVING failure_rate > 30%
    test_results-->>Scheduler: Flaky tests list
    
    loop For each flaky test
        Scheduler->>alerts: INSERT alert
        alerts-->>Scheduler: Alert created
        Scheduler->>Slack: POST webhook
        Slack-->>Scheduler: 200 OK
    end
```

## Index Usage Patterns

### Composite Index Examples

```sql
-- Index: idx_pr_repo_state_created (repository_id, state, created_at)
-- Covers these queries efficiently:

-- Query 1: Open PRs for a repo
SELECT * FROM pull_requests 
WHERE repository_id = 1 AND state = 'open' 
ORDER BY created_at DESC;

-- Query 2: Recent merged PRs
SELECT * FROM pull_requests 
WHERE repository_id = 1 AND state = 'merged' 
AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Query 3: Count by state
SELECT state, COUNT(*) FROM pull_requests 
WHERE repository_id = 1 
GROUP BY state;
```

### JSON Column Querying

```sql
-- Query with generated column (MySQL only)
SELECT * FROM webhook_events 
WHERE repository_id = 123  -- Uses generated column index
AND event_type = 'pull_request';

-- Query without generated column (PlanetScale compatible)
SELECT * FROM webhook_events 
WHERE JSON_EXTRACT(payload, '$.repository.id') = 123
AND event_type = 'pull_request';
-- Note: Slower, but works everywhere
```

## Data Flow Architecture

```mermaid
graph LR
    A[GitHub Webhook] -->|POST /webhooks| B[Nginx]
    B --> C[Laravel Controller]
    C -->|Store| D[webhook_events]
    C -->|Queue| E[Redis]
    E -->|Process| F[Horizon Worker]
    
    F -->|Parse| D
    F -->|Update| G[pull_requests]
    F -->|Create| H[test_runs]
    H -->|Insert| I[test_results]
    G -->|Analyze| J[file_changes]
    
    K[Scheduler] -->|Daily 1AM| L[Calculate Metrics]
    L -->|Aggregate| G
    L -->|Aggregate| H
    L -->|Insert| M[daily_metrics]
    
    N[Alert Rules] -->|Evaluate| I
    N -->|Trigger| O[alerts]
    O -->|Notify| P[Email/Slack]
    
    style A fill:#4CAF50
    style D fill:#FFC107
    style E fill:#FF5722
    style M fill:#2196F3
    style O fill:#9C27B0
```

## Storage Distribution (After 90 Days)

```
Total Database Size: ~9.5 GB

┌─────────────────────────────────────────────────────┐
│ test_results (70%)           6.75 GB                │
│ ███████████████████████████████████████████         │
├─────────────────────────────────────────────────────┤
│ test_runs (14%)              1.35 GB                │
│ ███████                                             │
├─────────────────────────────────────────────────────┤
│ webhook_events (3%)          300 MB                 │
│ ██                                                  │
├─────────────────────────────────────────────────────┤
│ file_changes (3%)            250 MB                 │
│ ██                                                  │
├─────────────────────────────────────────────────────┤
│ pull_requests (2%)           200 MB                 │
│ █                                                   │
├─────────────────────────────────────────────────────┤
│ Other tables (8%)            650 MB                 │
│ ████                                                │
└─────────────────────────────────────────────────────┘

With retention policies (90 days):
  - Old test_results pruned automatically
  - Old webhook_events pruned after 30 days
  - Stays within free-tier 10GB limit
```

## Performance Characteristics

| Operation | Complexity | Typical Time | Optimization |
|-----------|-----------|--------------|--------------|
| Insert PR | O(1) | 5-10ms | Indexed columns |
| Update PR metrics | O(1) | 10-20ms | Direct ID lookup |
| List PRs (paginated) | O(log n) | 20-50ms | Composite index |
| Search PRs | O(log n) | 10-30ms | Meilisearch |
| Insert test_run | O(1) | 10-20ms | Batch inserts |
| Insert 200 test_results | O(n) | 100-200ms | Chunked inserts |
| Calculate flakiness | O(n log n) | 200-500ms | Indexed test_identifier |
| Aggregate daily metrics | O(n) | 1-5 sec | Runs off-peak |
| Dashboard overview | O(1) | 10-20ms | Pre-aggregated |

---

## Migration Commands Reference

```bash
# Fresh migration (WARNING: drops all tables)
docker-compose exec app php artisan migrate:fresh

# Run migrations
docker-compose exec app php artisan migrate

# Rollback last batch
docker-compose exec app php artisan migrate:rollback

# Check migration status
docker-compose exec app php artisan migrate:status

# Seed database with test data
docker-compose exec app php artisan db:seed

# Fresh migration + seed
docker-compose exec app php artisan migrate:fresh --seed

# Create new migration
docker-compose exec app php artisan make:migration create_example_table
```

---

**Last Updated:** 2026-01-23  
**Schema Version:** 1.0  
**Total Tables:** 14  
**Total Indexes:** 87  
**Estimated Size (90 days):** 9.5 GB