# Step 2: Database Schema & Migrations - Testing Guide

## Overview
This guide verifies the database schema design, migrations, indexes, and seeding functionality. All tests must pass before proceeding to Step 3 (Webhook Processing).

---

## Prerequisites

- Step 1 completed successfully (all containers running)
- MySQL accessible on port 3307
- Laravel artisan commands functional

---

## Test Execution Steps

### Test 1: Verify Migration Files

**Action:** Check that all migration files exist

```bash
# List migration files
docker-compose exec app ls -la database/migrations/

# Expected files:
# - 2024_01_01_000001_create_repositories_table.php
# - 2024_01_01_000002_create_users_table.php
# - 2024_01_01_000003_create_pull_requests_table.php
# - 2024_01_01_000004_create_webhook_events_table.php
# - 2024_01_01_000005_create_test_runs_table.php
# - 2024_01_01_000006_create_test_results_table.php
# - 2024_01_01_000007_create_file_changes_table.php
# - 2024_01_01_000008_create_alert_rules_table.php
# - 2024_01_01_000009_create_alerts_table.php
# - 2024_01_01_000010_create_daily_metrics_table.php
# - 2024_01_01_000011_create_failed_jobs_table.php
# - 2024_01_01_000012_create_jobs_table.php
```

**Expected Results:**
✅ 12 migration files present
✅ Files named with proper timestamp prefix
✅ Files have proper PHP syntax

---

### Test 2: Run Fresh Migrations

**Action:** Run all migrations from scratch

```bash
# Drop all tables and re-run migrations
docker-compose exec app php artisan migrate:fresh
```

**Expected Results:**
✅ All migrations run successfully without errors
✅ No SQL errors or foreign key violations
✅ Output shows 12 migrations executed

**Expected Output:**
```
Dropped all tables successfully.
Migration table created successfully.
Migrating: 2024_01_01_000001_create_repositories_table
Migrated:  2024_01_01_000001_create_repositories_table (XX.XXms)
...
Migrating: 2024_01_01_000012_create_jobs_table
Migrated:  2024_01_01_000012_create_jobs_table (XX.XXms)
```

---

### Test 3: Verify Table Creation

**Action:** Check that all tables were created

```bash
# List all tables
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SHOW TABLES;"
```

**Expected Results:**
✅ 14 tables created (12 from migrations + migrations table + personal_access_tokens)

**Expected Tables:**
- alerts
- alert_rules
- daily_metrics
- failed_jobs
- file_changes
- jobs
- migrations
- pull_requests
- repositories
- test_results
- test_runs
- users
- webhook_events

---

### Test 4: Verify Table Structures

**Action:** Inspect key table structures

```bash
# Check repositories table
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "DESCRIBE repositories;"

# Check pull_requests table
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "DESCRIBE pull_requests;"

# Check test_runs table
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "DESCRIBE test_runs;"
```

**Expected Results:**
✅ All expected columns present
✅ Data types match specifications (BIGINT for IDs, JSON for payloads, etc.)
✅ NOT NULL constraints where appropriate
✅ Default values set correctly

**Verify Critical Fields:**

**repositories table:**
- `id` BIGINT PRIMARY KEY
- `external_id` BIGINT UNIQUE
- `full_name` VARCHAR(255)
- `deleted_at` TIMESTAMP NULL

**pull_requests table:**
- `id` BIGINT PRIMARY KEY
- `repository_id` BIGINT
- `number` INT
- `state` VARCHAR(20)
- `cycle_time` INT UNSIGNED NULL
- `deleted_at` TIMESTAMP NULL

**test_runs table:**
- `id` BIGINT PRIMARY KEY
- `repository_id` BIGINT
- `failed_tests_details` JSON NULL
- `started_at` TIMESTAMP NULL

---

### Test 5: Verify Indexes

**Action:** Check indexes on critical tables

```bash
# Show indexes on pull_requests (most queried table)
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SHOW INDEXES FROM pull_requests;"
```

**Expected Results:**
✅ Primary key index exists
✅ Foreign key indexes exist (repository_id, author_id)
✅ State and date indexes exist
✅ Composite indexes exist (repository_id + state + created_at)

**Critical Indexes to Verify:**
- PRIMARY (id)
- uq_repo_pr_number (repository_id, number) - UNIQUE
- idx_pr_repository_id
- idx_pr_state
- idx_pr_repo_state_created (composite)

---

### Test 6: Verify JSON Columns

**Action:** Test JSON column functionality

```bash
# Insert test record with JSON
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights << 'EOF'
INSERT INTO webhook_events (
    repository_id,
    event_type,
    delivery_id,
    signature,
    payload,
    created_at,
    updated_at
) VALUES (
    NULL,
    'test_event',
    'test-delivery-123',
    'sha256=test',
    '{"action": "opened", "number": 42, "repository": {"id": 123456}}',
    NOW(),
    NOW()
);

-- Query JSON field
SELECT 
    id,
    event_type,
    JSON_EXTRACT(payload, '$.number') as pr_number,
    JSON_EXTRACT(payload, '$.repository.id') as repo_id
FROM webhook_events 
WHERE delivery_id = 'test-delivery-123';

-- Cleanup
DELETE FROM webhook_events WHERE delivery_id = 'test-delivery-123';
EOF
```

**Expected Results:**
✅ INSERT succeeds without errors
✅ JSON_EXTRACT returns correct values (42, 123456)
✅ DELETE succeeds

---

### Test 7: Verify Unique Constraints

**Action:** Test unique constraints prevent duplicates

```bash
# Try to insert duplicate repository external_id
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights << 'EOF'
-- First insert should succeed
INSERT INTO repositories (external_id, provider, full_name, name, owner, html_url, created_at, updated_at)
VALUES (999999, 'github', 'test/repo', 'repo', 'test', 'https://github.com/test/repo', NOW(), NOW());

-- Second insert should fail (duplicate external_id)
INSERT INTO repositories (external_id, provider, full_name, name, owner, html_url, created_at, updated_at)
VALUES (999999, 'github', 'test/repo2', 'repo2', 'test', 'https://github.com/test/repo2', NOW(), NOW());
EOF
```

**Expected Results:**
✅ First INSERT succeeds
❌ Second INSERT fails with "Duplicate entry" error
✅ Error message mentions `external_id` key

**Cleanup:**
```bash
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "DELETE FROM repositories WHERE external_id = 999999;"
```

---

### Test 8: Verify Composite Unique Constraint

**Action:** Test repository_id + PR number uniqueness

```bash
# Test PR number uniqueness per repository
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights << 'EOF'
-- Insert test repository
INSERT INTO repositories (id, external_id, provider, full_name, name, owner, html_url, created_at, updated_at)
VALUES (999, 888888, 'github', 'test/unique', 'unique', 'test', 'https://github.com/test/unique', NOW(), NOW());

-- First PR #1 in repo 999 should succeed
INSERT INTO pull_requests (repository_id, external_id, number, state, title, head_branch, base_branch, head_sha, base_sha, html_url, created_at, updated_at)
VALUES (999, 1001, 1, 'open', 'Test PR', 'feature', 'main', SHA1('test1'), SHA1('base1'), 'url1', NOW(), NOW());

-- PR #1 in different repo should succeed
INSERT INTO repositories (id, external_id, provider, full_name, name, owner, html_url, created_at, updated_at)
VALUES (998, 777777, 'github', 'test/unique2', 'unique2', 'test', 'https://github.com/test/unique2', NOW(), NOW());

INSERT INTO pull_requests (repository_id, external_id, number, state, title, head_branch, base_branch, head_sha, base_sha, html_url, created_at, updated_at)
VALUES (998, 1002, 1, 'open', 'Test PR 2', 'feature', 'main', SHA1('test2'), SHA1('base2'), 'url2', NOW(), NOW());

-- Duplicate PR #1 in repo 999 should fail
INSERT INTO pull_requests (repository_id, external_id, number, state, title, head_branch, base_branch, head_sha, base_sha, html_url, created_at, updated_at)
VALUES (999, 1003, 1, 'open', 'Test PR 3', 'feature', 'main', SHA1('test3'), SHA1('base3'), 'url3', NOW(), NOW());
EOF
```

**Expected Results:**
✅ First PR #1 in repo 999 succeeds
✅ PR #1 in repo 998 succeeds (different repo)
❌ Duplicate PR #1 in repo 999 fails with "Duplicate entry" error

**Cleanup:**
```bash
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
DELETE FROM pull_requests WHERE repository_id IN (999, 998);
DELETE FROM repositories WHERE id IN (999, 998);
"
```

---

### Test 9: Verify Soft Deletes

**Action:** Test soft delete functionality

```bash
# Test soft delete on users table
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights << 'EOF'
-- Insert test user
INSERT INTO users (external_id, username, email, created_at, updated_at)
VALUES (555555, 'testuser', 'test@example.com', NOW(), NOW());

-- Soft delete (set deleted_at)
UPDATE users SET deleted_at = NOW() WHERE username = 'testuser';

-- Query all users (includes deleted)
SELECT id, username, deleted_at FROM users WHERE username = 'testuser';

-- Query only non-deleted users
SELECT id, username FROM users WHERE username = 'testuser' AND deleted_at IS NULL;

-- Cleanup
DELETE FROM users WHERE username = 'testuser';
EOF
```

**Expected Results:**
✅ INSERT succeeds
✅ UPDATE succeeds (soft delete)
✅ First SELECT returns user with `deleted_at` timestamp
✅ Second SELECT returns no rows (filtered by NULL deleted_at)
✅ Final DELETE succeeds (hard delete)

---

### Test 10: Verify Table Comments

**Action:** Check that table and column comments were added

```bash
# Check table comments
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights << 'EOF'
SELECT 
    TABLE_NAME,
    TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'ci_insights' 
  AND TABLE_NAME IN ('repositories', 'pull_requests', 'test_runs')
ORDER BY TABLE_NAME;
EOF

# Check column comments on pull_requests
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights << 'EOF'
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'ci_insights' 
  AND TABLE_NAME = 'pull_requests'
  AND COLUMN_COMMENT != ''
LIMIT 10;
EOF
```

**Expected Results:**
✅ All tables have meaningful comments
✅ Key columns have descriptive comments
✅ Comments explain purpose and usage

---

### Test 11: Run Database Seeder

**Action:** Populate database with test data

```bash
# Run seeder
docker-compose exec app php artisan db:seed
```

**Expected Results:**
✅ Seeder completes without errors
✅ Test data inserted into users, repositories, pull_requests

**Expected Output:**
```
Seeding development database...
Seeding users...
✓ Seeded 3 users
Seeding repositories...
✓ Seeded 2 repositories
Seeding pull requests...
✓ Seeded 50 pull requests
...
✓ Database seeded successfully!
```

**Verification:**
```bash
# Count records
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT 'users' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 'repositories', COUNT(*) FROM repositories
UNION ALL
SELECT 'pull_requests', COUNT(*) FROM pull_requests;
"
```

**Expected Counts:**
- users: 3
- repositories: 2
- pull_requests: 50

---

### Test 12: Verify Charset and Collation

**Action:** Check UTF8MB4 charset

```bash
# Check database charset
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME 
FROM information_schema.SCHEMATA 
WHERE SCHEMA_NAME = 'ci_insights';
"

# Check table charsets
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT 
    TABLE_NAME,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'ci_insights'
  AND TABLE_NAME IN ('users', 'pull_requests', 'repositories')
ORDER BY TABLE_NAME;
"
```

**Expected Results:**
✅ Database charset: `utf8mb4`
✅ Database collation: `utf8mb4_unicode_ci`
✅ All tables use `utf8mb4_unicode_ci`

---

### Test 13: Test Migration Rollback

**Action:** Verify migrations can be rolled back

```bash
# Rollback last batch of migrations
docker-compose exec app php artisan migrate:rollback

# Check remaining tables
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SHOW TABLES;"

# Re-run migrations
docker-compose exec app php artisan migrate
```

**Expected Results:**
✅ Rollback completes without errors
✅ Tables dropped in reverse order
✅ Re-running migrations recreates all tables
✅ No errors about existing tables

---

### Test 14: Performance Test - Index Usage

**Action:** Verify indexes are used in common queries

```bash
# Test index usage on pull_requests
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights << 'EOF'
-- Explain plan for common query
EXPLAIN SELECT * 
FROM pull_requests 
WHERE repository_id = 1 
  AND state = 'open' 
  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);
EOF
```

**Expected Results:**
✅ `type` is `ref` or `range` (not `ALL` = full table scan)
✅ `key` shows index name (e.g., `idx_pr_repo_state_created`)
✅ `rows` examined is reasonable (not entire table)

---

### Test 15: Data Integrity Test

**Action:** Verify data relationships work correctly

```bash
# Test querying related data
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights << 'EOF'
-- Query PRs with repository and author info
SELECT 
    pr.id,
    pr.number,
    pr.title,
    r.full_name as repository,
    u.username as author
FROM pull_requests pr
LEFT JOIN repositories r ON pr.repository_id = r.id
LEFT JOIN users u ON pr.author_id = u.id
LIMIT 5;
EOF
```

**Expected Results:**
✅ Query executes without errors
✅ JOINs return correct data
✅ LEFT JOINs handle NULL foreign keys gracefully

---

## Troubleshooting

### Issue: Migration fails with "Table already exists"

**Solution:**
```bash
# Drop all tables and start fresh
docker-compose exec app php artisan migrate:fresh
```

### Issue: "Access denied" errors

**Solution:**
```bash
# Verify database credentials in .env
docker-compose exec app php artisan env | grep DB_

# Test direct MySQL connection
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod -e "SELECT 1;"
```

### Issue: Seeder fails with foreign key errors

**Solution:**
```bash
# Ensure migrations ran first
docker-compose exec app php artisan migrate:status

# Run fresh migrations and seed
docker-compose exec app php artisan migrate:fresh --seed
```

---

## Success Criteria Checklist

Before proceeding to Step 3, verify ALL of the following:

- [ ] All 12 migrations execute successfully
- [ ] All 14 tables created (12 + migrations + personal_access_tokens)
- [ ] All indexes created correctly
- [ ] JSON columns store and query data correctly
- [ ] Unique constraints prevent duplicates
- [ ] Composite unique constraint works (repo + PR number)
- [ ] Soft deletes function properly
- [ ] Table and column comments present
- [ ] Database seeder runs successfully
- [ ] UTF8MB4 charset configured
- [ ] Migration rollback works
- [ ] Indexes are used in EXPLAIN queries
- [ ] Data relationships query correctly
- [ ] No SQL errors in any test
- [ ] All tests documented with timestamps

---

## Performance Benchmarks

Expected query performance on seeded data:

| Query | Expected Time | Command |
|-------|---------------|---------|
| SELECT all PRs for repo | < 10ms | `SELECT * FROM pull_requests WHERE repository_id = 1 LIMIT 100;` |
| SELECT PRs with filters | < 20ms | `SELECT * FROM pull_requests WHERE repository_id = 1 AND state = 'open';` |
| JOIN PRs + repos + users | < 50ms | `SELECT * FROM pull_requests pr JOIN repositories r ON ... LIMIT 20;` |
| COUNT aggregate query | < 100ms | `SELECT repository_id, COUNT(*) FROM pull_requests GROUP BY repository_id;` |

---

**Testing completed on:** [Date]  
**Tested by:** [Your Name]  
**Database version:** MySQL 8.0.x  
**Status:** ✅ PASS / ❌ FAIL  
**Notes:** [Any observations or issues encountered]