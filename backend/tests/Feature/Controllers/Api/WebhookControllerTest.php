<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Models\Repository;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Webhook Controller Test
 *
 * @package Tests\Feature\Controllers\Api
 */
class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private Repository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test repository
        $this->repository = Repository::factory()->create([
            'external_id' => 123,
            'name' => 'test-repo',
            'full_name' => 'owner/test-repo',
        ]);

        // Set webhook secret for testing
        Config::set('services.github.webhook_secret', 'test-secret');
    }

    public function test_github_webhook_accepts_valid_request(): void
    {
        $payload = [
            'action' => 'opened',
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/test-repo',
                'html_url' => 'https://github.com/owner/test-repo',
            ],
            'pull_request' => [
                'id' => 456,
                'number' => 1,
                'state' => 'open',
                'title' => 'Test PR',
                'html_url' => 'https://github.com/owner/test-repo/pull/1',
                'user' => [
                    'id' => 789,
                    'login' => 'testuser',
                ],
                'head' => ['ref' => 'feature-branch', 'sha' => 'abc123'],
                'base' => ['ref' => 'main', 'sha' => 'def456'],
                'additions' => 10,
                'deletions' => 5,
                'changed_files' => 3,
            ],
        ];

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test-secret');

        $response = $this->postJson('/api/webhooks/github', $payload, [
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'accepted',
                'message' => 'Webhook received and queued for processing',
            ]);

        // Verify webhook event was stored
        $this->assertDatabaseHas('webhook_events', [
            'repository_id' => $this->repository->id,
            'provider' => 'github',
            'event_type' => 'pull_request',
            'action' => 'opened',
            'delivery_id' => '550e8400-e29b-41d4-a716-446655440000',
            'signature_verified' => true,
            'status' => 'pending',
        ]);
    }

    public function test_github_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'repository' => ['id' => 123],
        ];

        $response = $this->postJson('/api/webhooks/github', $payload, [
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => 'sha256=invalid-signature',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Invalid signature',
            ]);
    }

    public function test_github_webhook_rejects_missing_headers(): void
    {
        $payload = [
            'repository' => ['id' => 123],
        ];

        $response = $this->postJson('/api/webhooks/github', $payload);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Missing required headers',
            ]);
    }

    public function test_github_webhook_handles_duplicate_delivery(): void
    {
        // Create existing webhook event
        WebhookEvent::factory()->create([
            'repository_id' => $this->repository->id,
            'delivery_id' => '550e8400-e29b-41d4-a716-446655440000',
            'status' => 'completed',
        ]);

        $payload = [
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/test-repo',
                'html_url' => 'https://github.com/owner/test-repo',
            ],
        ];

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test-secret');

        $response = $this->postJson('/api/webhooks/github', $payload, [
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'duplicate',
                'message' => 'Webhook already processed',
            ]);
    }

    public function test_github_webhook_processes_check_run_event(): void
    {
        $payload = [
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/test-repo',
                'html_url' => 'https://github.com/owner/test-repo',
            ],
            'check_run' => [
                'id' => 456,
                'name' => 'CI Tests',
                'status' => 'completed',
                'conclusion' => 'success',
                'head_sha' => 'abc123def4567890123456789012345678901234',
            ],
        ];

        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), 'test-secret');

        $response = $this->postJson('/api/webhooks/github', $payload, [
            'X-GitHub-Event' => 'check_run',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440001',
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertStatus(202);

        // Verify webhook event was stored
        $this->assertDatabaseHas('webhook_events', [
            'repository_id' => $this->repository->id,
            'event_type' => 'check_run',
            'action' => 'completed',
            'status' => 'pending',
        ]);
    }

    public function test_test_webhook_endpoint_works_in_non_production(): void
    {
        // Ensure we're not in production
        $this->assertNotEquals('production', app()->environment());

        $payload = [
            'event_type' => 'test_event',
            'action' => 'test_action',
            'repository_id' => $this->repository->id,
        ];

        $response = $this->postJson('/api/webhooks/test', $payload);

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'accepted',
                'message' => 'Test webhook queued',
            ]);

        // Verify test event was stored
        $this->assertDatabaseHas('webhook_events', [
            'repository_id' => $this->repository->id,
            'provider' => 'test',
            'event_type' => 'test_event',
            'action' => 'test_action',
            'status' => 'pending',
        ]);
    }

    public function test_test_webhook_endpoint_forbidden_in_production(): void
    {
        // Mock production environment
        app()->detectEnvironment(fn() => 'production');

        $response = $this->postJson('/api/webhooks/test', []);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'message' => 'Test endpoint not available in production',
            ]);
    }

    public function test_gitlab_webhook_returns_not_implemented(): void
    {
        $response = $this->postJson('/api/webhooks/gitlab', []);

        $response->assertStatus(501)
            ->assertJson([
                'error' => 'Not implemented',
                'message' => 'GitLab webhooks are not yet supported',
            ]);
    }
}