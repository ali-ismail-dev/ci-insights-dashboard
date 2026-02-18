# ADR 002: Database Schema Design

**Status:** Accepted  
**Date:** 2026-01-23  
**Decision Makers:** Engineering Team, Database Architect  
**Related ADRs:** [ADR 001: Docker Environment Setup](001-docker-environment-setup.md)

---

## Context and Problem Statement

We need a database schema that efficiently handles:
- **High-volume webhook ingestion** (100+ per hour)
- **PR analysis with complex metrics** (cycle time, review duration, CI status)
- **Time-series test data** (1000s of test runs per day)
- **Flakiness detection** (track test history across runs)
- **File-level CI correlation** (which files cause failures)
- **Real-time dashboard queries** (< 100ms response time)
- **Free-tier constraints** (< 10GB storage, optimize for query performance)

The schema must balance:
1. **Normalization** (data integrity, no duplication)
2. **Denormalization** (query performance, avoid complex JOINs)
3. **Flexibility** (handle evolving GitHub webhook schemas)
4. **PlanetScale compatibility** (optional foreign keys, no generated columns)

---

## Decision Drivers

1. **Query Performance:** Dashboard queries must be < 100ms (95th percentile)
2. **Storage Efficiency:** Stay within free-tier limits (< 10GB)
3. **Data Integrity:** Prevent orphaned records and inconsistent state
4. **Flexibility:** Support multiple Git providers (GitHub, GitLab, Bitbucket)
5. **PlanetScale Migration:** Design allows migration to PlanetScale (no FKs)
6. **Maintainability:** Clear relationships, documented schema
7. **Scalability:** Support 100K+ PRs, 1M+ test results

---

## Database Architecture Decisions

### 1. No Foreign Key Constraints

**Decision:** Define relationships in schema but do NOT enforce with FK constraints

**Rationale:**
- **PlanetScale compatibility:** Vitess (PlanetScale's engine) doesn't support FKs
- **Flexibility:** Easier to migrate between MySQL providers
- **Performance:** FK checks add overhead on high-volume inserts

**Trade-offs:**
- ✅ **Pro:** Can deploy to PlanetScale, Railway, or AWS RDS without schema changes
- ✅ **Pro:** Faster INSERTs (no FK validation overhead)
- ❌ **Con:** Must enforce referential integrity at application level
- ❌ **Con:** Risk of orphaned records if application logic fails

**Mitigation:**
```php
// Enforce at application level
class PullRequest extends Model
{
    protected static function boot()
    {
        parent::boot();
        
        // Prevent deletion if dependent records exist
        static::deleting(function ($pr) {
            if ($pr->testRuns()->exists()) {
                throw new \Exception('Cannot delete PR with test runs');
            }
        });
    }
}

// Background job validates referential integrity
class ValidateReferentialIntegrity extends Command
{
    public function handle()
    {
        // Find orphaned test_runs (repository_id doesn't exist)
        $orphaned = DB::select("
            SELECT tr.id FROM test_runs tr
            LEFT JOIN repositories r ON tr.repository_id = r.id
            WHERE r.id IS NULL
        ");
        
        // Delete or alert
        foreach ($orphaned as $record) {
            $this->error("Orphaned test_run: {$record->id}");
        }
    }
}
```

---

### 2. Soft Deletes on Core Entities

**Decision:** Use `deleted_at` column on repositories, users, pull_requests, alert_rules

**Rationale:**
- **Audit trail:** Track what was deleted and when
- **Data recovery:** Restore accidentally deleted records
- **Referential integrity:** Child records don't become orphaned
- **Analytics:** Include deleted PRs in historical metrics

**Implementation:**
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class PullRequest extends Model
{
    use SoftDeletes;
    
    // Automatically scopes queries to non-deleted records
    // PullRequest::all() excludes deleted_at != NULL
    
    // Include deleted records
    // PullRequest::withTrashed()->get()
    
    // Only deleted records
    // PullRequest::onlyTrashed()->get()
}
```

**Trade-offs:**
- ✅ **Pro:** Safer than hard deletes (reversible)
- ✅ **Pro:** Maintains data relationships
- ❌ **Con:** Unique constraints need special handling
- ❌ **Con:** Storage overhead (deleted records remain)

**Unique Constraint Handling:**
```sql
-- Traditional unique constraint (breaks after soft delete)
UNIQUE (email)

-- Solution: Allow multiple soft-deleted records
UNIQUE (email, deleted_at)

-- Or use composite index + application logic
CREATE UNIQUE INDEX idx_email_active 
ON users (email) 
WHERE deleted_at IS NULL;  -- PostgreSQL only

-- MySQL workaround: application-level validation
```

---

### 3. JSON Columns for Flexible Schema

**Decision:** Use JSON columns for:
- `webhook_events.payload` (full GitHub webhook)
- `test_runs.failed_tests_details` (test failures array)
- `alert_rules.conditions` (rule configuration)
- `users.permissions` (granular permissions)

**Rationale:**
- **Flexibility:** GitHub adds new webhook fields frequently
- **Avoid EAV anti-pattern:** Don't create `entity_attributes` table
- **Single query retrieval:** One SELECT gets all data
- **Indexable:** Generated columns allow indexing JSON fields

**Example:**
```sql
CREATE TABLE webhook_events (
    id BIGINT PRIMARY KEY,
    payload JSON,
    -- Generated column for fast queries (MySQL only, not PlanetScale)
    repository_id BIGINT AS (payload->>'$.repository.id') STORED,
    INDEX idx_repository_id (repository_id)
);
```

**Trade-offs:**
- ✅ **Pro:** Handles evolving schemas (GitHub API changes)
- ✅ **Pro:** No schema migrations for new fields
- ✅ **Pro:** Single query retrieves all data
- ❌ **Con:** No schema validation at database level
- ❌ **Con:** Slower queries on JSON fields (vs indexed columns)
- ❌ **Con:** Generated columns not supported on PlanetScale

**Validation Strategy:**
```php
// Validate JSON structure at application level
class WebhookEventRequest extends FormRequest
{
    public function rules()
    {
        return [
            'payload' => ['required', 'json'],
            'payload.action' => ['required', 'string'],
            'payload.repository.id' => ['required', 'integer'],
            'payload.pull_request.number' => ['required', 'integer'],
        ];
    }
}
```

---

### 4. Composite Indexes for Analytics Queries

**Decision:** Create composite indexes on common query patterns

**Rationale:**
- **Query performance:** Composite indexes cover WHERE + ORDER BY
- **Free-tier optimization:** Avoid full table scans
- **Dashboard speed:** < 100ms for most queries

**Index Strategy:**
```sql
-- Single-column indexes (basic lookups)
INDEX idx_pr_repository_id (repository_id)
INDEX idx_pr_state (state)
INDEX idx_pr_created_at (created_at)

-- Composite indexes (dashboard queries)
INDEX idx_pr_repo_state_created (repository_id, state, created_at)
-- Covers: WHERE repository_id = X AND state = 'open' ORDER BY created_at DESC

INDEX idx_pr_repo_merged (repository_id, merged_at)
-- Covers: WHERE repository_id = X AND merged_at IS NOT NULL

INDEX idx_pr_state_stale_updated (state, is_stale, updated_at)
-- Covers: WHERE state = 'open' AND is_stale = true ORDER BY updated_at
```

**Index Usage Example:**
```sql
-- This query uses idx_pr_repo_state_created
EXPLAIN SELECT * 
FROM pull_requests 
WHERE repository_id = 1 
  AND state = 'open' 
  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY created_at DESC
LIMIT 20;

-- Result:
-- type: range
-- key: idx_pr_repo_state_created
-- rows: ~50 (not 10,000)
```

**Trade-offs:**
- ✅ **Pro:** 10-100x faster queries
- ✅ **Pro:** Reduces database CPU usage
- ❌ **Con:** Slower INSERTs (index maintenance)
- ❌ **Con:** More storage (indexes consume space)
- ❌ **Con:** Requires maintenance (analyze, rebuild)

**Index Maintenance:**
```bash
# Daily scheduled job
docker-compose exec app php artisan db:analyze-indexes

# Check index usage
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    CARDINALITY,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size(MB)'
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'ci_insights'
ORDER BY Size(MB) DESC;
```

---

### 5. Denormalized Metrics for Performance

**Decision:** Store pre-calculated metrics in `daily_metrics` table

**Rationale:**
- **Fast dashboard loading:** Avoid complex aggregations on every page load
- **Simulates materialized views:** MySQL doesn't have native MVs
- **Historical trends:** Track metrics over time without recalculating

**Example:**
```sql
-- Normalized approach (SLOW)
SELECT 
    DATE(created_at) as date,
    COUNT(*) as prs_opened,
    AVG(cycle_time) as avg_cycle_time
FROM pull_requests
WHERE repository_id = 1
  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at);
-- Execution time: 200-500ms (scans entire table)

-- Denormalized approach (FAST)
SELECT 
    metric_date,
    prs_opened,
    avg_cycle_time
FROM daily_metrics
WHERE repository_id = 1
  AND metric_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY metric_date;
-- Execution time: 5-10ms (indexed lookup)
```

**Update Strategy:**
```php
// Scheduled job runs daily at 1 AM
class CalculateDailyMetrics extends Command
{
    public function handle()
    {
        $yesterday = now()->subDay()->toDateString();
        
        foreach (Repository::active()->get() as $repo) {
            DailyMetric::updateOrCreate(
                [
                    'repository_id' => $repo->id,
                    'metric_date' => $yesterday,
                ],
                [
                    'prs_opened' => $repo->pullRequests()
                        ->whereDate('created_at', $yesterday)->count(),
                    'prs_merged' => $repo->pullRequests()
                        ->whereDate('merged_at', $yesterday)->count(),
                    'avg_cycle_time' => $repo->pullRequests()
                        ->whereDate('merged_at', $yesterday)
                        ->avg('cycle_time'),
                    'is_final' => true,
                    'calculated_at' => now(),
                ]
            );
        }
    }
}
```

**Trade-offs:**
- ✅ **Pro:** 20-100x faster dashboard queries
- ✅ **Pro:** Reduces database load during peak hours
- ✅ **Pro:** Historical data always available
- ❌ **Con:** Data staleness (up to 24 hours)
- ❌ **Con:** Storage overhead (one row per repo per day)
- ❌ **Con:** Complexity (two sources of truth)

---

### 6. Time-Series Data Strategy

**Decision:** Use Laravel's `Prunable` trait for automatic data retention

**Rationale:**
- **Free-tier survival:** Test runs accumulate fast (1000s per day)
- **Performance:** Smaller tables = faster queries
- **Compliance:** Automatic data retention policies

**Implementation:**
```php
use Illuminate\Database\Eloquent\Prunable;

class TestRun extends Model
{
    use Prunable;
    
    public function prunable()
    {
        // Delete test runs older than 90 days
        return static::where('created_at', '<=', now()->subDays(90));
    }
    
    protected function pruning()
    {
        // Archive to S3 before deletion (optional)
        $this->archiveToS3();
    }
}

// Scheduled command runs daily
// Schedule::command('model:prune')->daily();
```

**Retention Policies:**
| Table | Retention | Rationale |
|-------|-----------|-----------|
| `webhook_events` | 30 days | Audit trail, replay capability |
| `test_runs` | 90 days | Flakiness detection window |
| `test_results` | 90 days | Historical flakiness scores |
| `alerts` | 180 days | Compliance, alert analytics |
| `daily_metrics` | Indefinite | Small, historical trends |
| `pull_requests` | Indefinite | Core data, soft deletes |

**Partitioning (Future Optimization):**
```sql
-- If test_runs exceeds 1M records, partition by month
ALTER TABLE test_runs
PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
    PARTITION p202601 VALUES LESS THAN (202602),
    PARTITION p202602 VALUES LESS THAN (202603),
    PARTITION p202603 VALUES LESS THAN (202604),
    -- Auto-add partitions via script
);
```

**Trade-offs:**
- ✅ **Pro:** Automatic cleanup (no manual maintenance)
- ✅ **Pro:** Stays within free-tier storage limits
- ✅ **Pro:** Faster queries (smaller tables)
- ❌ **Con:** Historical data loss (intentional)
- ❌ **Con:** Partitioning not supported on PlanetScale

---

### 7. Idempotency via Unique Delivery ID

**Decision:** Use `webhook_events.delivery_id` as unique constraint

**Rationale:**
- **Prevent duplicate processing:** GitHub may retry webhooks
- **Safe replays:** Re-send webhook without side effects
- **Audit trail:** Track all webhook deliveries

**Implementation:**
```php
public function processWebhook(Request $request)
{
    $deliveryId = $request->header('X-GitHub-Delivery');
    
    // Try to insert webhook event
    try {
        $event = WebhookEvent::create([
            'delivery_id' => $deliveryId,
            'event_type' => $request->header('X-GitHub-Event'),
            'payload' => $request->all(),
            'signature' => $request->header('X-Hub-Signature-256'),
        ]);
        
        // Process webhook
        ProcessWebhookJob::dispatch($event);
        
        return response()->json(['status' => 'accepted'], 202);
        
    } catch (UniqueConstraintViolationException $e) {
        // Duplicate webhook delivery, already processed
        return response()->json(['status' => 'duplicate'], 200);
    }
}
```

**Trade-offs:**
- ✅ **Pro:** Idempotent webhook processing
- ✅ **Pro:** Safe to replay webhooks
- ✅ **Pro:** Audit trail of all deliveries
- ❌ **Con:** Storage overhead (keep all webhook payloads)

---

## Schema Entity Descriptions

### Core Entities

**repositories**
- Purpose: Tracked GitHub/GitLab repositories
- Volume: Low (< 100)
- Growth: Stable
- Key fields: `external_id`, `full_name`, `webhook_secret`

**users**
- Purpose: Dashboard users + PR contributors
- Volume: Low-Medium (< 1000)
- Growth: Steady (2-5 per week)
- Key fields: `external_id`, `username`, `oauth_token`

**pull_requests**
- Purpose: PRs with comprehensive metrics
- Volume: Medium-High (10K-100K)
- Growth: 50-200 per day
- Key fields: `repository_id`, `number`, `cycle_time`, `ci_status`

### Time-Series Entities

**test_runs**
- Purpose: CI test execution records
- Volume: High (100K-1M)
- Growth: 1000-5000 per day
- Retention: 90 days
- Key fields: `repository_id`, `pull_request_id`, `status`, `duration`

**test_results**
- Purpose: Individual test results for flakiness
- Volume: Very High (1M-10M)
- Growth: 10K-100K per day
- Retention: 90 days
- Key fields: `test_run_id`, `test_identifier`, `status`, `is_flaky`

### Event Entities

**webhook_events**
- Purpose: Webhook receipts with full payload
- Volume: High (50K-500K)
- Growth: 100-1000 per day
- Retention: 30 days
- Key fields: `delivery_id`, `event_type`, `payload`

**alerts**
- Purpose: Triggered alerts with context
- Volume: Medium (10K-50K)
- Growth: 10-100 per day
- Retention: 180 days
- Key fields: `alert_rule_id`, `status`, `context`

### Analytical Entities

**file_changes**
- Purpose: File modifications per PR
- Volume: High (100K-1M)
- Growth: 500-5000 per day
- Retention: Indefinite (linked to PRs)
- Key fields: `pull_request_id`, `file_path`, `caused_ci_failure`

**daily_metrics**
- Purpose: Pre-aggregated metrics
- Volume: Low-Medium (< 50K)
- Growth: 1 per repo per day
- Retention: Indefinite
- Key fields: `repository_id`, `metric_date`, `prs_opened`, `ci_success_rate`

---

## Storage Estimation

### Storage per Record (Average)

| Table | Size/Record | Records/Day | Daily Growth |
|-------|-------------|-------------|--------------|
| repositories | 1 KB | 0.1 | 0.1 KB |
| users | 1 KB | 0.5 | 0.5 KB |
| pull_requests | 2 KB | 50 | 100 KB |
| webhook_events | 10 KB | 100 | 1 MB |
| test_runs | 5 KB | 1000 | 5 MB |
| test_results | 0.5 KB | 50000 | 25 MB |
| file_changes | 0.5 KB | 500 | 250 KB |
| alerts | 2 KB | 10 | 20 KB |
| daily_metrics | 1 KB | 1 | 1 KB |

**Total daily growth:** ~31 MB/day

**With retention policies:**
- webhook_events (30 days): 30 MB
- test_runs (90 days): 450 MB
- test_results (90 days): 2.25 GB
- Other tables: ~500 MB

**Total storage:** ~3.5 GB (well within free-tier 10GB limit)

---

## Query Performance Targets

| Query Type | Target | Optimization |
|------------|--------|--------------|
| Dashboard overview | < 50ms | Pre-aggregated metrics |
| PR list (paginated) | < 100ms | Composite indexes |
| PR detail + tests | < 150ms | Eager loading |
| Search (Meilisearch) | < 50ms | External search engine |
| Flaky test report | < 200ms | Indexed test_identifier |
| File failure correlation | < 300ms | Denormalized flags |
| Historical trends (30 days) | < 100ms | daily_metrics table |

---

## Migration Path

### From MySQL (with FKs) to PlanetScale (no FKs)

**Step 1:** Export schema
```bash
mysqldump --no-data --skip-add-drop-table ci_insights > schema.sql
```

**Step 2:** Remove FK constraints from schema
```bash
sed -i '/FOREIGN KEY/d' schema.sql
sed -i '/REFERENCES/d' schema.sql
```

**Step 3:** Remove generated columns (PlanetScale doesn't support)
```sql
-- Before
repository_id BIGINT AS (payload->>'$.repository.id') STORED

-- After (compute in application)
SELECT JSON_EXTRACT(payload, '$.repository.id') as repository_id
```

**Step 4:** Import to PlanetScale
```bash
pscale database create ci_insights
pscale shell ci_insights main < schema.sql
```

**Step 5:** Update application (enforce integrity in code)
```php
// Add validation in model events
static::creating(function ($model) {
    if (!Repository::find($model->repository_id)) {
        throw new \Exception('Repository not found');
    }
});
```

---

## Testing Strategy

### Schema Testing

```php
// tests/Feature/Schema/RepositorySchemaTest.php
public function test_repositories_table_exists()
{
    $this->assertTrue(Schema::hasTable('repositories'));
}

public function test_repositories_has_required_columns()
{
    $this->assertTrue(Schema::hasColumns('repositories', [
        'id', 'external_id', 'full_name', 'webhook_secret'
    ]));
}

public function test_repositories_external_id_is_unique()
{
    $repo1 = Repository::factory()->create(['external_id' => 123456]);
    
    $this->expectException(QueryException::class);
    Repository::factory()->create(['external_id' => 123456]);
}
```

### Performance Testing

```php
// tests/Performance/QueryPerformanceTest.php
public function test_pr_list_query_is_fast()
{
    PullRequest::factory()->count(1000)->create();
    
    $start = microtime(true);
    
    $prs = PullRequest::where('repository_id', 1)
        ->where('state', 'open')
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();
    
    $duration = (microtime(true) - $start) * 1000; // ms
    
    $this->assertLessThan(100, $duration, "Query took {$duration}ms");
}
```

---

## Future Optimizations

### 1. Read Replicas (when outgrowing free tier)

```env
DB_READ_HOST=replica.example.com
DB_WRITE_HOST=primary.example.com
```

```php
// config/database.php
'mysql' => [
    'read' => ['host' => env('DB_READ_HOST')],
    'write' => ['host' => env('DB_WRITE_HOST')],
]
```

### 2. Horizontal Sharding (if > 10M PRs)

```php
// Shard by repository_id
$shard = $repository_id % 4; // 4 shards

// config/database.php
'mysql_shard_0' => [...],
'mysql_shard_1' => [...],
'mysql_shard_2' => [...],
'mysql_shard_3' => [...],
```

### 3. Caching Layer (Redis)

```php
// Cache PR list for 5 minutes
$prs = Cache::remember("repo.{$repoId}.prs.open", 300, function () use ($repoId) {
    return PullRequest::where('repository_id', $repoId)
        ->where('state', 'open')
        ->get();
});
```

---

## Conclusion

This schema design prioritizes:
1. **Query performance** via composite indexes and pre-aggregated metrics
2. **Flexibility** via JSON columns and no FK constraints
3. **Storage efficiency** via retention policies and pruning
4. **PlanetScale compatibility** via application-level referential integrity
5. **Maintainability** via clear documentation and migration strategies

The design supports free-tier deployment while providing a clear path to scale horizontally as the system grows.

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-23  
**Next Review:** After Step 3 (Webhook Processing Implementation)