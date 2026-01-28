# Step 1: Docker Environment & Laravel Setup - Testing Guide

## Overview
This document provides comprehensive testing procedures for verifying the Docker environment and Laravel project initialization. Follow each step sequentially to ensure the foundation is solid before proceeding to the next phase.

---

## Prerequisites

Before running tests, ensure you have:
- Docker Desktop installed and running
- Docker Compose installed (or `docker compose` plugin)
- At least 4GB of free RAM
- At least 10GB of free disk space
- Git installed
- Terminal/Command line access

---

## Test Execution Steps

### 1. Project Initialization

**Action:** Clone/Create project structure and run setup script

```bash
# Make setup script executable
chmod +x setup.sh

# Run setup script
./setup.sh
```

**Expected Results:**
✅ Script completes without errors
✅ All prerequisites checks pass
✅ Laravel project created in `backend/` directory
✅ All Docker containers start successfully
✅ Success message displayed with service URLs

**Verification Commands:**
```bash
# Check if all containers are running
docker-compose ps

# Should show 7 containers in "Up" state:
# - ci_insights_app
# - ci_insights_nginx
# - ci_insights_mysql
# - ci_insights_redis
# - ci_insights_meilisearch
# - ci_insights_horizon
# - ci_insights_scheduler
```

**Expected Output:**
```
NAME                      STATUS          PORTS
ci_insights_app           Up (healthy)    9000/tcp
ci_insights_nginx         Up (healthy)    0.0.0.0:8080->80/tcp
ci_insights_mysql         Up (healthy)    0.0.0.0:3307->3306/tcp
ci_insights_redis         Up (healthy)    0.0.0.0:6380->6379/tcp
ci_insights_meilisearch   Up              0.0.0.0:7700->7700/tcp
ci_insights_horizon       Up (healthy)    
ci_insights_scheduler     Up              
```

---

### 2. Database Connectivity Test

**Action:** Verify MySQL connection and database creation

```bash
# Test MySQL connection
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod -e "SHOW DATABASES;"
```

**Expected Results:**
✅ Connection succeeds without authentication errors
✅ Databases `ci_insights` and `ci_insights_test` are listed
✅ Character set is `utf8mb4`

**Expected Output:**
```
+--------------------+
| Database           |
+--------------------+
| ci_insights        |
| ci_insights_test   |
| information_schema |
+--------------------+
```

**Verification:**
```bash
# Verify database charset
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SELECT @@character_set_database, @@collation_database;"
```

**Expected Output:**
```
+--------------------------+----------------------+
| @@character_set_database | @@collation_database |
+--------------------------+----------------------+
| utf8mb4                  | utf8mb4_unicode_ci   |
+--------------------------+----------------------+
```

---

### 3. Redis Connectivity Test

**Action:** Verify Redis connection and persistence

```bash
# Test Redis connection
docker-compose exec redis redis-cli ping

# Set and get a test value
docker-compose exec redis redis-cli SET test_key "test_value"
docker-compose exec redis redis-cli GET test_key

# Check Redis info
docker-compose exec redis redis-cli INFO server | grep redis_version
```

**Expected Results:**
✅ `PONG` response from ping command
✅ Test value stored and retrieved successfully
✅ Redis version 7.x displayed

**Expected Output:**
```
PONG
OK
"test_value"
redis_version:7.2.x
```

---

### 4. Meilisearch Connectivity Test

**Action:** Verify Meilisearch health and version

```bash
# Check Meilisearch health
curl http://localhost:7700/health

# Check Meilisearch version
curl http://localhost:7700/version
```

**Expected Results:**
✅ Health endpoint returns `{"status":"available"}`
✅ Version endpoint returns version information

**Expected Output:**
```json
{"status":"available"}

{
  "commitSha":"xxxxxxx",
  "commitDate":"2024-xx-xx",
  "pkgVersion":"1.5.x"
}
```

---

### 5. Laravel Application Test

**Action:** Verify Laravel installation and configuration

```bash
# Check Laravel version
docker-compose exec app php artisan --version

# Check environment configuration
docker-compose exec app php artisan env

# Verify database connection
docker-compose exec app php artisan migrate:status
```

**Expected Results:**
✅ Laravel 11.x version displayed
✅ Environment shows as "local"
✅ Database migrations table created and empty (or with initial migrations)

**Expected Output:**
```
Laravel Framework 11.x.x

Current application environment: local

Migration table created successfully.
+------+------------------------------------------------+-------+
| Ran? | Migration                                      | Batch |
+------+------------------------------------------------+-------+
```

---

### 6. Web Server Access Test

**Action:** Verify Nginx is serving Laravel application

```bash
# Test health endpoint
curl http://localhost:8080/health

# Test Laravel default route
curl http://localhost:8080/
```

**Expected Results:**
✅ Health endpoint returns "healthy"
✅ Default route returns Laravel welcome page HTML

**Expected Output:**
```
healthy

<!DOCTYPE html>
<html lang="en">
...Laravel welcome page HTML...
```

**Browser Test:**
- Open `http://localhost:8080` in your browser
- Should see Laravel default welcome page

---

### 7. Queue System Test (Horizon)

**Action:** Verify Laravel Horizon is running

```bash
# Check Horizon status
docker-compose exec app php artisan horizon:status

# Check Horizon logs
docker-compose logs horizon --tail=20
```

**Expected Results:**
✅ Horizon reports as "running"
✅ No error messages in logs
✅ Master supervisor process active

**Expected Output:**
```
Horizon is running.

[timestamp] Horizon started successfully.
[timestamp] Processing jobs from [default] queue...
```

---

### 8. Scheduler Test

**Action:** Verify Laravel scheduler is running

```bash
# Check scheduler logs
docker-compose logs scheduler --tail=20

# Should see schedule:run commands executing every minute
```

**Expected Results:**
✅ Schedule runner executing every ~60 seconds
✅ No errors in output

**Expected Output:**
```
[timestamp] No scheduled commands are ready to run.
[timestamp] No scheduled commands are ready to run.
...
```

---

### 9. PHP Configuration Test

**Action:** Verify PHP extensions and configuration

```bash
# Check PHP version and extensions
docker-compose exec app php -v
docker-compose exec app php -m | grep -E "(redis|pdo_mysql|opcache|gd|bcmath)"

# Check PHP configuration
docker-compose exec app php -i | grep -E "(memory_limit|max_execution_time|upload_max_filesize)"
```

**Expected Results:**
✅ PHP 8.2.x displayed
✅ All required extensions installed: redis, pdo_mysql, opcache, gd, bcmath
✅ Memory limit: 256M
✅ Max execution time: 60
✅ Upload max filesize: 20M

**Expected Output:**
```
PHP 8.2.x

redis
pdo_mysql
opcache
gd
bcmath

memory_limit => 256M => 256M
max_execution_time => 60 => 60
upload_max_filesize => 20M => 20M
```

---

### 10. Storage Permissions Test

**Action:** Verify writable directories

```bash
# Test writing to storage
docker-compose exec app touch storage/logs/test.log
docker-compose exec app ls -la storage/logs/test.log
docker-compose exec app rm storage/logs/test.log

# Test writing to cache
docker-compose exec app touch bootstrap/cache/test.php
docker-compose exec app ls -la bootstrap/cache/test.php
docker-compose exec app rm bootstrap/cache/test.php
```

**Expected Results:**
✅ Files created successfully
✅ Files have proper permissions (644 or 664)
✅ Files deleted successfully

---

### 11. Composer Autoloading Test

**Action:** Verify Composer autoloader is working

```bash
# Dump autoload
docker-compose exec app composer dump-autoload

# Run Composer validation
docker-compose exec app composer validate
```

**Expected Results:**
✅ Autoload completes without errors
✅ Composer.json and composer.lock are valid

**Expected Output:**
```
Generating optimized autoload files
> Illuminate\Foundation\ComposerScripts::postAutoloadDump
> @php artisan package:discover --ansi
Discovered Package: ...

./composer.json is valid
./composer.lock is valid
```

---

### 12. Container Resource Usage Test

**Action:** Verify containers are within free-tier resource limits

```bash
# Check container stats
docker stats --no-stream
```

**Expected Results:**
✅ Total memory usage < 2GB
✅ No container using > 500MB individually
✅ CPU usage reasonable (< 50% sustained)

**Expected Output (approximate):**
```
CONTAINER              CPU %    MEM USAGE / LIMIT     MEM %
ci_insights_app        1-5%     100-200MB / 4GB       2-5%
ci_insights_mysql      2-10%    256-400MB / 4GB       6-10%
ci_insights_redis      0-2%     20-50MB / 4GB         0.5-1%
ci_insights_meilisearch 1-3%    50-150MB / 4GB        1-4%
ci_insights_nginx      0-1%     10-20MB / 4GB         0.3%
ci_insights_horizon    1-5%     50-150MB / 4GB        1-4%
ci_insights_scheduler  0-1%     30-80MB / 4GB         0.7-2%
```

---

### 13. Log Files Test

**Action:** Verify logging is working correctly

```bash
# Check Laravel logs
docker-compose exec app tail -20 storage/logs/laravel.log

# Check Nginx access logs
docker-compose logs nginx --tail=20

# Check MySQL error logs
docker-compose exec mysql tail -20 /var/log/mysql/error.log
```

**Expected Results:**
✅ Log files exist and are writable
✅ Recent entries visible (timestamps recent)
✅ No critical errors

---

### 14. Network Connectivity Test

**Action:** Verify internal Docker network communication

```bash
# From app container, ping other services
docker-compose exec app ping -c 3 mysql
docker-compose exec app ping -c 3 redis
docker-compose exec app ping -c 3 meilisearch

# Test DNS resolution
docker-compose exec app nslookup mysql
```

**Expected Results:**
✅ All services respond to ping
✅ DNS resolution works for service names
✅ No packet loss

---

### 15. Volume Persistence Test

**Action:** Verify data persists across container restarts

```bash
# Create test data
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "CREATE TABLE test_persistence (id INT PRIMARY KEY, value VARCHAR(50));"
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "INSERT INTO test_persistence VALUES (1, 'persistence_test');"

# Restart MySQL container
docker-compose restart mysql

# Wait for MySQL to be ready (30 seconds)
sleep 30

# Verify data still exists
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SELECT * FROM test_persistence;"

# Cleanup
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "DROP TABLE test_persistence;"
```

**Expected Results:**
✅ Data persists after container restart
✅ Test row retrieved successfully

**Expected Output:**
```
+----+-----------------+
| id | value           |
+----+-----------------+
|  1 | persistence_test|
+----+-----------------+
```

---

## Troubleshooting Common Issues

### Issue: Containers fail to start

**Symptoms:** `docker-compose ps` shows containers in "Exit" state

**Solutions:**
```bash
# Check logs for specific container
docker-compose logs <container_name>

# Common fixes:
# 1. Port already in use
sudo lsof -i :8080  # Check what's using port 8080
sudo lsof -i :3307  # Check what's using port 3307

# 2. Insufficient resources
# Increase Docker Desktop memory allocation to 4GB+

# 3. Permission issues
chmod -R 775 backend/storage
chmod -R 775 backend/bootstrap/cache
```

### Issue: MySQL connection refused

**Symptoms:** Laravel cannot connect to MySQL

**Solutions:**
```bash
# Wait for MySQL to fully start (can take 60s on first run)
docker-compose logs mysql | grep "ready for connections"

# Verify MySQL is healthy
docker-compose ps mysql  # Should show "healthy"

# Test direct connection
docker-compose exec mysql mysql -uroot -proot_password_change_in_prod -e "SELECT 1;"
```

### Issue: Composer install fails

**Symptoms:** Dependencies not installing

**Solutions:**
```bash
# Clear Composer cache
docker-compose exec app composer clear-cache

# Retry with verbose output
docker-compose exec app composer install -vvv

# Check for memory issues
docker-compose exec app php -i | grep memory_limit
```

### Issue: Permission denied errors

**Symptoms:** Cannot write to storage or cache

**Solutions:**
```bash
# From host machine
chmod -R 775 backend/storage
chmod -R 775 backend/bootstrap/cache

# If on Linux, fix ownership
sudo chown -R $USER:$USER backend/
```

---

## Success Criteria Checklist

Before proceeding to Step 2, verify ALL of the following:

- [ ] All 7 Docker containers running and healthy
- [ ] MySQL accessible on port 3307 with correct credentials
- [ ] Redis accessible on port 6380 and responding to ping
- [ ] Meilisearch accessible on port 7700 and showing healthy status
- [ ] Laravel welcome page visible at http://localhost:8080
- [ ] Laravel artisan commands execute without errors
- [ ] Horizon status shows "running"
- [ ] Scheduler executing every minute
- [ ] Database migrations table created
- [ ] All required PHP extensions installed (redis, pdo_mysql, opcache, gd, bcmath)
- [ ] Storage and cache directories writable
- [ ] Container memory usage under 2GB total
- [ ] Volume persistence verified (data survives container restart)
- [ ] Internal network communication working
- [ ] No critical errors in any log files

---

## Performance Benchmarks

Expected baseline performance metrics:

| Metric | Expected Value | Command to Measure |
|--------|---------------|-------------------|
| Laravel boot time | < 100ms | `docker-compose exec app php artisan optimize && time curl http://localhost:8080/` |
| Database query time | < 10ms | `docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SELECT SLEEP(0); SELECT NOW();" \| grep -A1 NOW` |
| Redis ping latency | < 1ms | `docker-compose exec redis redis-cli --latency -i 1` |
| Container startup time | < 60s | `time docker-compose up -d` |

---

## Next Steps

Once all tests pass:

1. **Document any deviations** from expected results
2. **Save container logs** for reference:
   ```bash
   docker-compose logs > initial_setup_logs.txt
   ```
3. **Create a snapshot** (optional):
   ```bash
   docker commit ci_insights_app ci_insights_app:step1_complete
   ```
4. **Proceed to Step 2**: Database schema design and migrations

---

## Cleanup Commands (if you need to start over)

```bash
# Stop and remove all containers
docker-compose down

# Remove all volumes (WARNING: deletes all data)
docker-compose down -v

# Remove images
docker-compose down --rmi all

# Full cleanup
docker system prune -a --volumes

# Then re-run setup
./setup.sh
```

---

**Testing completed on:** [Date]  
**Tested by:** [Your Name]  
**Environment:** [OS and Docker version]  
**Status:** ✅ PASS / ❌ FAIL  
**Notes:** [Any observations or issues encountered]