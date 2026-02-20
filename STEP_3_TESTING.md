# Step 3: Webhook Processing & Background Jobs - Testing Guide

## Overview
This guide verifies the webhook ingestion system, signature verification, job processing, and data flow from webhooks to database. All tests must pass before proceeding to Step 4.

---

## Prerequisites

- Step 1 completed (Docker environment running)
- Step 2 completed (Database schema and migrations)
- Laravel Horizon running (`docker-compose ps horizon` shows "Up")
- Redis accessible on port 6380

---

## Test Execution Steps

### Test 1: Verify Files Exist

**Action:** Check that all implementation files are present

```bash
# Check controller
docker-compose exec app ls -la app/Http/Controllers/Api/WebhookController.php

# Check job
docker-compose exec app ls -la app/Jobs/ProcessWebhookJob.php

# Check actions
docker-compose exec app ls -la app/Actions/PullRequest/CreateOrUpdatePullRequestAction.php
docker-compose exec app ls -la app/Actions/TestRun/ProcessTestRunAction.php

# Check routes
docker-compose exec app grep "webhooks/github" routes/api.php
```

**Expected Results:**
✅ All files exist
✅ Route defined in routes/api.php
✅ No PHP syntax errors

---

### Test 2: Configure GitHub Webhook Secret

**Action:** Set webhook secret in environment

```bash
# Edit .env file
docker-compose exec app bash -c 'echo "GITHUB_WEBHOOK_SECRET=test_secret_12345" >> .env'

# Verify configuration
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan tinker --execute="echo config('services.github.webhook_secret');"
```

**Expected Results:**
✅ Secret configured correctly
✅ Config cache cleared

**Alternative: Update config/services.php**
```php
return [
    'github' => [
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        // ... other GitHub configs
    ],
];
```

---

### Test 3: Test Webhook Endpoint Accessibility

**Action:** Verify endpoint responds

```bash
# Test endpoint exists (should return 400 - missing headers)
curl -X POST http://localhost:8080/api/webhooks/github \
  -H "Content-Type: application/json" \
  -d '{}' \
  -w "\nHTTP Status: %{http_code}\n"
```

**Expected Results:**
✅ HTTP Status: 400 (Bad Request)
✅ Error message: "Missing required headers"

**Expected Response:**
```json
{
  "error": "Missing required headers",
  "message": "X-GitHub-Event, X-GitHub-Delivery, and X-Hub-Signature-256 are required"
}
```

---

### Test 4: Test Signature Verification (Invalid Signature)

**Action:** Send webhook with invalid signature

```bash
curl -X POST http://localhost:8080/api/webhooks/github \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: pull_request" \
  -H "X-GitHub-Delivery: test-delivery-001" \
  -H "X-Hub-Signature-256: sha256=invalid_signature" \
  -d '{"action": "opened", "number": 1}' \
  -w "\nHTTP Status: %{http_code}\n"
```

**Expected Results:**
✅ HTTP Status: 403 (Forbidden)
✅ Error message: "Invalid signature"

**Expected Response:**
```json
{
  "error": "Invalid signature",
  "message": "Webhook signature verification failed"
}
```

---

### Test 5: Test Valid Webhook with Correct Signature

**Action:** Send webhook with correctly calculated HMAC signature

```bash
# Calculate correct signature
SECRET="test_secret_12345"
PAYLOAD='{"action":"opened","number":1,"pull_request":{"id":123,"number":1,"title":"Test PR","state":"open","user":{"id":999,"login":"testuser"},"head":{"ref":"feature","sha":"abc123"},"base":{"ref":"main","sha":"def456"},"html_url":"https://github.com/test/repo/pull/1"},"repository":{"id":1}}'

SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

curl -X POST http://localhost:8080/api/webhooks/github \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: pull_request" \
  -H "X-GitHub-Delivery: test-delivery-valid-001" \
  -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
  -d "$PAYLOAD" \
  -w "\nHTTP Status: %{http_code}\n"
```

**Expected Results:**
✅ HTTP Status: 202 (Accepted)
✅ Response includes `event_id` and `delivery_id`
✅ Response time < 100ms

**Expected Response:**
```json
{
  "status": "accepted",
  "message": "Webhook received and queued for processing",
  "event_id": 1,
  "delivery_id": "test-delivery-valid-001",
  "response_time_ms": 45.23
}
```

**Verification:**
```bash
# Check webhook_events table
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT id, event_type, delivery_id, status, signature_verified 
FROM webhook_events 
WHERE delivery_id = 'test-delivery-valid-001';
"
```

**Expected Database Record:**
```
+----+--------------+---------------------------+---------+--------------------+
| id | event_type   | delivery_id               | status  | signature_verified |
+----+--------------+---------------------------+---------+--------------------+
|  1 | pull_request | test-delivery-valid-001   | pending |                  1 |
+----+--------------+---------------------------+---------+--------------------+
```

---

### Test 6: Test Idempotency (Duplicate Webhook)

**Action:** Send same webhook twice with same delivery_id

```bash
# Send same webhook again
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

curl -X POST http://localhost:8080/api/webhooks/github \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: pull_request" \
  -H "X-GitHub-Delivery: test-delivery-valid-001" \
  -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
  -d "$PAYLOAD" \
  -w "\nHTTP Status: %{http_code}\n"
```

**Expected Results:**
✅ HTTP Status: 200 (OK)
✅ Response status: "duplicate"
✅ No new database record created

**Expected Response:**
```json
{
  "status": "duplicate",
  "message": "Webhook already processed",
  "event_id": 1
}
```

---

### Test 7: Verify Job Queued in Redis

**Action:** Check that job was queued

```bash
# Check Redis for queued jobs
docker-compose exec redis redis-cli KEYS "laravel_database_queues:*"

# Check queue length
docker-compose exec redis redis-cli LLEN "laravel_database_queues:high"
docker-compose exec redis redis-cli LLEN "laravel_database_queues:default"
```

**Expected Results:**
✅ Job exists in Redis queue
✅ Queue length > 0

---

### Test 8: Verify Horizon Status

**Action:** Check Horizon is processing jobs

```bash
# Check Horizon status
docker-compose exec app php artisan horizon:status

# View Horizon logs
docker-compose logs horizon --tail=50

# Access Horizon dashboard
open http://localhost:8080/horizon
```

**Expected Results:**
✅ Horizon status: "running"
✅ Workers processing jobs
✅ Dashboard accessible

**Horizon Dashboard Checks:**
- Jobs tab shows processed jobs
- Failed Jobs tab is empty (or shows retrying jobs)
- Metrics show job throughput

---

### Test 9: Wait for Job Processing

**Action:** Wait for job to process (should be fast)

```bash
# Wait 5 seconds
sleep 5

# Check webhook_events status
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT id, event_type, status, processed_at, processing_duration 
FROM webhook_events 
WHERE delivery_id = 'test-delivery-valid-001';
"
```

**Expected Results:**
✅ Status changed from "pending" to "completed"
✅ `processed_at` timestamp is set
✅ `processing_duration` < 1000ms

**Expected Output:**
```
+----+--------------+-----------+---------------------+---------------------+
| id | event_type   | status    | processed_at        | processing_duration |
+----+--------------+-----------+---------------------+---------------------+
|  1 | pull_request | completed | 2026-01-23 10:30:15 |                 245 |
+----+--------------+-----------+---------------------+---------------------+
```

---

### Test 10: Verify Pull Request Created

**Action:** Check that PR was created in database

```bash
# Check pull_requests table
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT id, repository_id, number, title, state, author_id 
FROM pull_requests 
WHERE number = 1;
"
```

**Expected Results:**
✅ PR record created
✅ Fields populated from webhook payload
✅ Foreign keys set correctly

**Expected Output:**
```
+----+---------------+--------+----------+-------+-----------+
| id | repository_id | number | title    | state | author_id |
+----+---------------+--------+----------+-------+-----------+
|  1 |             1 |      1 | Test PR  | open  |         1 |
+----+---------------+--------+----------+-------+-----------+
```

---

### Test 11: Verify User Created

**Action:** Check that PR author was created

```bash
# Check users table
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT id, external_id, username, provider 
FROM users 
WHERE username = 'testuser';
"
```

**Expected Results:**
✅ User record created
✅ `external_id` matches webhook payload
✅ Provider set to "github"

---

### Test 12: Test Failed Job Handling

**Action:** Send webhook that will fail processing

```bash
# Send webhook with invalid data (missing required fields)
PAYLOAD_BAD='{"action":"opened"}'
SIGNATURE=$(echo -n "$PAYLOAD_BAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

curl -X POST http://localhost:8080/api/webhooks/github \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: pull_request" \
  -H "X-GitHub-Delivery: test-delivery-fail-001" \
  -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
  -d "$PAYLOAD_BAD" \
  -w "\nHTTP Status: %{http_code}\n"

# Wait for job to fail
sleep 5

# Check webhook_events status
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT id, event_type, status, error_message, retry_count 
FROM webhook_events 
WHERE delivery_id = 'test-delivery-fail-001';
"
```

**Expected Results:**
✅ Webhook accepted (202)
✅ Job attempted processing
✅ Status: "failed" or "pending" (if retrying)
✅ `error_message` populated
✅ `retry_count` incremented

**Check Failed Jobs:**
```bash
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT id, uuid, exception 
FROM failed_jobs 
ORDER BY failed_at DESC 
LIMIT 1;
"
```

---

### Test 13: Test Job Retry

**Action:** Manually retry failed job

```bash
# Retry all failed jobs
docker-compose exec app php artisan queue:retry all

# Or retry specific UUID
docker-compose exec app php artisan queue:retry <uuid>

# Wait and check status
sleep 5

docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "
SELECT retry_count FROM webhook_events WHERE delivery_id = 'test-delivery-fail-001';
"
```

**Expected Results:**
✅ Job requeued
✅ `retry_count` incremented
✅ Either succeeds or fails again (expected to fail with bad data)

---

### Test 14: Test Rate Limiting

**Action:** Send many webhooks rapidly

```bash
# Send 100 webhooks in rapid succession
for i in {1..100}; do
  PAYLOAD_SMALL='{"action":"synchronize","number":'"$i"',"pull_request":{"id":'"$i"',"number":'"$i"'},"repository":{"id":1}}'
  SIGNATURE=$(echo -n "$PAYLOAD_SMALL" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
  
  curl -X POST http://localhost:8080/api/webhooks/github \
    -H "Content-Type: application/json" \
    -H "X-GitHub-Event: pull_request" \
    -H "X-GitHub-Delivery: test-delivery-rate-$i" \
    -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
    -d "$PAYLOAD_SMALL" \
    -w "%{http_code}\n" \
    -s -o /dev/null &
done

wait
```

**Expected Results:**
✅ Most requests return 202 (Accepted)
✅ Some requests may return 429 (Too Many Requests) if rate limit exceeded
✅ System remains stable (no crashes)

**Check Rate Limit:**
```bash
# Check if any requests were rate limited
grep "429" /tmp/rate_limit_test.log
```

---

### Test 15: Performance Test - Response Time

**Action:** Measure webhook response time

```bash
# Send webhook and measure time
PAYLOAD='{"action":"opened","number":999,"pull_request":{"id":999,"number":999,"title":"Perf Test","state":"open","user":{"id":888,"login":"perfuser"},"head":{"ref":"feature","sha":"perf123"},"base":{"ref":"main","sha":"perf456"},"html_url":"https://github.com/test/repo/pull/999"},"repository":{"id":1}}'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

time curl -X POST http://localhost:8080/api/webhooks/github \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: pull_request" \
  -H "X-GitHub-Delivery: test-delivery-perf-001" \
  -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
  -d "$PAYLOAD" \
  -w "\nResponse time: %{time_total}s\n"
```

**Expected Results:**
✅ Response time < 0.100 seconds (100ms)
✅ Response includes `response_time_ms` field

**Performance Targets:**
- P50 (median): < 50ms
- P95: < 100ms
- P99: < 200ms

---

### Test 16: Test Different Event Types

**Action:** Send various GitHub event types

```bash
# Test pull_request_review event
PAYLOAD_REVIEW='{"action":"submitted","review":{"id":1,"state":"approved"},"pull_request":{"id":1,"number":1},"repository":{"id":1}}'
SIGNATURE=$(echo -n "$PAYLOAD_REVIEW" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

curl -X POST http://localhost:8080/api/webhooks/github \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: pull_request_review" \
  -H "X-GitHub-Delivery: test-delivery-review-001" \
  -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
  -d "$PAYLOAD_REVIEW"

# Test check_run event (CI status)
PAYLOAD_CHECK='{"action":"completed","check_run":{"id":1,"name":"CI","status":"completed","conclusion":"success","head_sha":"abc123"},"repository":{"id":1}}'
SIGNATURE=$(echo -n "$PAYLOAD_CHECK" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

curl -X POST http://localhost:8080/api/webhooks/github \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: check_run" \
  -H "X-GitHub-Delivery: test-delivery-check-001" \
  -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
  -d "$PAYLOAD_CHECK"
```

**Expected Results:**
✅ Both events accepted (202)
✅ Different queue priorities based on event type
✅ Events stored in `webhook_events` table

---

### Test 17: Verify Queue Priorities

**Action:** Check that jobs are in correct queues

```bash
# Check queue distribution
docker-compose exec redis redis-cli << EOF
LLEN laravel_database_queues:high
LLEN laravel_database_queues:default
LLEN laravel_database_queues:low
EOF

# Check Horizon for queue metrics
docker-compose exec app php artisan horizon:list
```

**Expected Results:**
✅ High-priority events (PR opened) in "high" queue
✅ Regular events in "default" queue
✅ Background tasks in "low" queue

---

### Test 18: Test Webhook Logs

**Action:** Verify logging

```bash
# Check Laravel logs for webhook events
docker-compose exec app tail -50 storage/logs/laravel.log | grep -i webhook

# Check for specific log entries
docker-compose exec app grep "Webhook event stored successfully" storage/logs/laravel.log | tail -5
docker-compose exec app grep "Webhook processing job dispatched" storage/logs/laravel.log | tail -5
docker-compose exec app grep "Pull request processed" storage/logs/laravel.log | tail -5
```

**Expected Results:**
✅ Structured log entries present
✅ Include event_id, delivery_id, event_type
✅ No errors or warnings (except intentional test failures)

---

### Test 19: End-to-End Verification

**Action:** Verify complete data flow

```bash
# Send complete PR webhook
PAYLOAD_FULL=$(cat << 'EOF'
{
  "action": "opened",
  "number": 100,
  "pull_request": {
    "id": 100,
    "number": 100,
    "title": "E2E Test PR",
    "body": "This is a test PR for end-to-end verification",
    "state": "open",
    "draft": false,
    "user": {
      "id": 12345,
      "login": "e2e-tester",
      "avatar_url": "https://avatars.githubusercontent.com/u/12345"
    },
    "head": {
      "ref": "feature/e2e-test",
      "sha": "e2e123456789abcdef"
    },
    "base": {
      "ref": "main",
      "sha": "main123456789abcdef"
    },
    "html_url": "https://github.com/test/repo/pull/100",
    "additions": 150,
    "deletions": 50,
    "changed_files": 5,
    "commits": 3,
    "comments": 0,
    "labels": [{"name": "enhancement"}],
    "assignees": [],
    "requested_reviewers": [],
    "created_at": "2026-01-23T10:00:00Z",
    "updated_at": "2026-01-23T10:00:00Z"
  },
  "repository": {
    "id": 1
  }
}
EOF
)

SIGNATURE=$(echo -n "$PAYLOAD_FULL" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

curl -X POST http://localhost:8080/api/webhooks/github \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: pull_request" \
  -H "X-GitHub-Delivery: test-delivery-e2e-001" \
  -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
  -d "$PAYLOAD_FULL"

# Wait for processing
sleep 10

# Verify complete data chain
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights << 'SQL'
-- Check webhook event
SELECT 'webhook_events' as table_name, id, status 
FROM webhook_events 
WHERE delivery_id = 'test-delivery-e2e-001'
UNION ALL
-- Check pull request
SELECT 'pull_requests', id, state 
FROM pull_requests 
WHERE number = 100
UNION ALL
-- Check user
SELECT 'users', id, username 
FROM users 
WHERE username = 'e2e-tester';
SQL
```

**Expected Results:**
✅ Webhook event: status = "completed"
✅ Pull request: created with all fields populated
✅ User: created with correct external_id
✅ All foreign keys linked correctly

---

## Troubleshooting

### Issue: Jobs not processing

**Solution:**
```bash
# Check Horizon is running
docker-compose ps horizon

# Restart Horizon
docker-compose restart horizon

# Check for errors
docker-compose logs horizon --tail=100

# Manually process queue
docker-compose exec app php artisan queue:work --once
```

### Issue: Signature verification fails

**Solution:**
```bash
# Verify secret is configured
docker-compose exec app php artisan tinker --execute="echo config('services.github.webhook_secret');"

# Check if secret matches between request and config
# Ensure you're using the raw payload (before JSON parsing) for signature calculation
```

### Issue: Jobs fail immediately

**Solution:**
```bash
# Check failed jobs
docker-compose exec mysql mysql -uci_user -pci_secure_password_change_in_prod ci_insights -e "SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 1\G"

# Check error logs
docker-compose exec app tail -100 storage/logs/laravel.log | grep ERROR

# Retry with verbose output
docker-compose exec app php artisan queue:work --once -vvv
```

---

## Success Criteria Checklist

Before proceeding to Step 4, verify ALL of the following:

- [ ] Webhook endpoint responds correctly
- [ ] Signature verification works (rejects invalid signatures)
- [ ] Valid webhooks return 202 Accepted
- [ ] Idempotency prevents duplicate processing
- [ ] Jobs queue in Redis
- [ ] Horizon processes jobs successfully
- [ ] Webhook events stored with correct status
- [ ] Pull requests created/updated from webhooks
- [ ] Users created from webhook payloads
- [ ] Failed jobs retry with backoff
- [ ] Rate limiting works (429 on excessive requests)
- [ ] Response time < 100ms (P95)
- [ ] Different event types processed correctly
- [ ] Queue priorities assigned correctly
- [ ] Logs contain structured webhook entries
- [ ] End-to-end verification passes
- [ ] No memory leaks or performance degradation

---

## Performance Benchmarks

Expected performance metrics:

| Metric | Target | Command |
|--------|--------|---------|
| Webhook response time (P50) | < 50ms | `curl -w "%{time_total}"` |
| Webhook response time (P95) | < 100ms | Load test with `ab` or `wrk` |
| Job processing time (simple) | < 1s | Check `processing_duration` in DB |
| Job processing time (complex) | < 30s | For PR with file analysis |
| Jobs throughput | 100+ jobs/min | Horizon metrics dashboard |
| Failed job rate | < 1% | `SELECT COUNT(*) FROM failed_jobs` |

---

**Testing completed on:** [Date]  
**Tested by:** [Your Name]  
**Environment:** Docker Compose on [OS]  
**Status:** ✅ PASS / ❌ FAIL  
**Notes:** [Any observations or issues encountered]