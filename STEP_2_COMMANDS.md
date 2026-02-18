# Step 2: Quick Command Reference

## Setup Commands

```bash
# Place migration files in backend/database/migrations/
# Then run:

# Run all migrations
docker-compose exec app php artisan migrate

# Fresh migrations (drops all tables first)
docker-compose exec app php artisan migrate:fresh

# Fresh migrations + seed test data
docker-compose exec app php artisan migrate:fresh --seed
```

## Verification Commands

```bash
# Check migration status
docker-compose exec app php artisan migrate:status

# List all tables
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SHOW TABLES;"

# Describe a table structure
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "DESCRIBE pull_requests;"

# Show indexes on a table
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SHOW INDEXES FROM pull_requests;"

# Count records in all tables
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights << 'EOF'
SELECT 
    table_name,
    table_rows
FROM information_schema.tables
WHERE table_schema = 'ci_insights'
ORDER BY table_rows DESC;
EOF
```

## Testing Commands

```bash
# Test JSON column functionality
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
INSERT INTO webhook_events (repository_id, event_type, delivery_id, signature, payload, created_at, updated_at) 
VALUES (1, 'test', 'test-123', 'sig', '{\"test\": \"data\"}', NOW(), NOW());

SELECT JSON_EXTRACT(payload, '$.test') FROM webhook_events WHERE delivery_id = 'test-123';

DELETE FROM webhook_events WHERE delivery_id = 'test-123';
"

# Check database charset
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME 
FROM information_schema.SCHEMATA 
WHERE SCHEMA_NAME = 'ci_insights';
"

# Get database size
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'ci_insights'
GROUP BY table_schema;
"
```

## Rollback Commands

```bash
# Rollback last migration batch
docker-compose exec app php artisan migrate:rollback

# Rollback specific number of migrations
docker-compose exec app php artisan migrate:rollback --step=3

# Rollback all migrations
docker-compose exec app php artisan migrate:reset
```

## Data Management

```bash
# Seed database
docker-compose exec app php artisan db:seed

# Seed specific seeder
docker-compose exec app php artisan db:seed --class=RepositorySeeder

# Truncate and reseed (keeps schema)
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE users;
TRUNCATE repositories;
TRUNCATE pull_requests;
SET FOREIGN_KEY_CHECKS = 1;
"
docker-compose exec app php artisan db:seed
```

## Backup & Restore

```bash
# Backup database schema
docker-compose exec mysql mysqldump -uci_user -pci_secure_password_change_in_prod --no-data ci_insights > schema_backup.sql

# Backup database with data
docker-compose exec mysql mysqldump -uci_user -pci_secure_password_change_in_prod ci_insights > full_backup.sql

# Restore database
docker-compose exec -T mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights < full_backup.sql
```

## Performance Testing

```bash
# Analyze query performance
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
EXPLAIN SELECT * 
FROM pull_requests 
WHERE repository_id = 1 
  AND state = 'open' 
ORDER BY created_at DESC 
LIMIT 20;
"

# Check index usage
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT 
    INDEX_NAME,
    CARDINALITY,
    ROUND((INDEX_LENGTH / 1024 / 1024), 2) AS 'Size(MB)'
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'ci_insights'
  AND TABLE_NAME = 'pull_requests'
GROUP BY INDEX_NAME
ORDER BY Size(MB) DESC;
"
```

## Troubleshooting

```bash
# Check for orphaned records (no FK enforcement)
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT COUNT(*) as orphaned_prs
FROM pull_requests pr
LEFT JOIN repositories r ON pr.repository_id = r.id
WHERE r.id IS NULL;
"

# Find tables without indexes
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT DISTINCT TABLE_NAME
FROM information_schema.TABLES t
WHERE TABLE_SCHEMA = 'ci_insights'
  AND NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS s
    WHERE s.TABLE_SCHEMA = t.TABLE_SCHEMA
      AND s.TABLE_NAME = t.TABLE_NAME
      AND s.INDEX_NAME != 'PRIMARY'
  );
"

# Check slow queries (if slow query log enabled)
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod -e "
SELECT * FROM mysql.slow_log ORDER BY query_time DESC LIMIT 10;
"
```

## Maintenance Commands

```bash
# Optimize tables
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
OPTIMIZE TABLE pull_requests, test_runs, test_results;
"

# Analyze tables (update index statistics)
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
ANALYZE TABLE pull_requests, test_runs, test_results;
"

# Check table fragmentation
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT 
    TABLE_NAME,
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS 'Size(MB)',
    ROUND(DATA_FREE / 1024 / 1024, 2) AS 'Free(MB)',
    ROUND((DATA_FREE / (DATA_LENGTH + INDEX_LENGTH)) * 100, 2) AS 'Fragmentation(%)'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'ci_insights'
  AND DATA_FREE > 0
ORDER BY Fragmentation(%) DESC;
"
```

## Quick Test Sequence

Run these commands in order to verify Step 2 completion:

```bash
# 1. Fresh migration
docker-compose exec app php artisan migrate:fresh

# 2. Verify tables created
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SHOW TABLES;" | wc -l
# Expected: 14 (plus header line)

# 3. Seed test data
docker-compose exec app php artisan db:seed

# 4. Verify data inserted
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT 'users' as tbl, COUNT(*) as cnt FROM users
UNION SELECT 'repositories', COUNT(*) FROM repositories
UNION SELECT 'pull_requests', COUNT(*) FROM pull_requests;
"
# Expected: users=3, repositories=2, pull_requests=50

# 5. Test query performance
time docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT * FROM pull_requests WHERE repository_id = 1 LIMIT 20;
" > /dev/null
# Expected: < 100ms

# 6. Check database size
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'ci_insights';
"
# Expected: < 50 MB (with test data)
```

## Success Indicators

✅ **All migrations run successfully** (no errors)  
✅ **14 tables created** (12 migrations + migrations table + personal_access_tokens)  
✅ **Test data seeded** (3 users, 2 repos, 50 PRs)  
✅ **Indexes created** (check SHOW INDEXES)  
✅ **UTF8MB4 charset** (check with SHOW CREATE TABLE)  
✅ **Queries use indexes** (check with EXPLAIN)  
✅ **Database size < 50MB** (with test data)

---

**Ready for Step 3:** Once all success indicators pass, proceed to Webhook Processing implementation.