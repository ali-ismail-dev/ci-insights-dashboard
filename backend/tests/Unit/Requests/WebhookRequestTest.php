<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\WebhookRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Webhook Request Test
 *
 * @package Tests\Unit\Requests
 */
class WebhookRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createRequest(array $headers = [], array $payload = []): WebhookRequest
    {
        $request = new WebhookRequest();

        // Set headers
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        // Set payload
        $request->merge($payload);

        return $request;
    }

    public function test_validates_github_headers(): void
    {
        $request = $this->createRequest([
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => 'sha256=abc123def456',
        ], [
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/repo',
                'html_url' => 'https://github.com/owner/repo',
            ],
        ]);

        $this->assertTrue($request->authorize());
        $validator = validator($request->all(), $request->rules());

        // Should pass basic validation
        $this->assertFalse($validator->fails());
    }

    public function test_fails_without_required_github_headers(): void
    {
        $request = $this->createRequest([], [
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/repo',
                'html_url' => 'https://github.com/owner/repo',
            ],
        ]);

        $validator = validator($request->all(), $request->rules());
        $this->assertTrue($validator->fails());

        $errors = $validator->errors();
        $this->assertTrue($errors->has('X-GitHub-Event'));
        $this->assertTrue($errors->has('X-GitHub-Delivery'));
        $this->assertTrue($errors->has('X-Hub-Signature-256'));
    }

    public function test_validates_pull_request_payload(): void
    {
        $request = $this->createRequest([
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => 'sha256=abc123def456',
        ], [
            'action' => 'opened',
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/repo',
                'html_url' => 'https://github.com/owner/repo',
            ],
            'pull_request' => [
                'id' => 456,
                'number' => 1,
                'state' => 'open',
                'title' => 'Test PR',
                'body' => 'Description',
                'html_url' => 'https://github.com/owner/repo/pull/1',
                'user' => [
                    'id' => 789,
                    'login' => 'testuser',
                ],
                'head' => ['ref' => 'feature-branch'],
                'base' => ['ref' => 'main'],
            ],
        ]);

        $validator = validator($request->all(), $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_fails_pull_request_validation_without_required_fields(): void
    {
        $request = $this->createRequest([
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => 'sha256=abc123def456',
        ], [
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/repo',
                'html_url' => 'https://github.com/owner/repo',
            ],
            'pull_request' => [
                // Missing required fields
                'title' => 'Test PR',
            ],
        ]);

        $validator = validator($request->all(), $request->rules());
        $this->assertTrue($validator->fails());

        $errors = $validator->errors();
        $this->assertTrue($errors->has('pull_request.id'));
        $this->assertTrue($errors->has('pull_request.number'));
        $this->assertTrue($errors->has('pull_request.state'));
    }

    public function test_validates_check_run_payload(): void
    {
        $request = $this->createRequest([
            'X-GitHub-Event' => 'check_run',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => 'sha256=abc123def456',
        ], [
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/repo',
                'html_url' => 'https://github.com/owner/repo',
            ],
            'check_run' => [
                'id' => 101112,
                'name' => 'CI Tests',
                'status' => 'completed',
                'conclusion' => 'success',
            ],
        ]);

        $validator = validator($request->all(), $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_fails_check_run_validation_with_invalid_conclusion(): void
    {
        $request = $this->createRequest([
            'X-GitHub-Event' => 'check_run',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => 'sha256=abc123def456',
        ], [
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/repo',
                'html_url' => 'https://github.com/owner/repo',
            ],
            'check_run' => [
                'id' => 101112,
                'name' => 'CI Tests',
                'status' => 'completed',
                'conclusion' => 'invalid_status', // Invalid
            ],
        ]);

        $validator = validator($request->all(), $request->rules());
        $this->assertTrue($validator->fails());

        $errors = $validator->errors();
        $this->assertTrue($errors->has('check_run.conclusion'));
    }

    public function test_validates_status_event_payload(): void
    {
        $request = $this->createRequest([
            'X-GitHub-Event' => 'status',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => 'sha256=abc123def456',
        ], [
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/repo',
                'html_url' => 'https://github.com/owner/repo',
            ],
            'sha' => 'abc123def4567890123456789012345678901234',
            'state' => 'success',
        ]);

        $validator = validator($request->all(), $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_validates_push_event_payload(): void
    {
        $request = $this->createRequest([
            'X-GitHub-Event' => 'push',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => 'sha256=abc123def456',
        ], [
            'repository' => [
                'id' => 123,
                'full_name' => 'owner/repo',
                'html_url' => 'https://github.com/owner/repo',
            ],
            'ref' => 'refs/heads/main',
            'before' => 'abc123def4567890123456789012345678901234',
            'after' => 'def456789012345678901234567890123456789012',
            'commits' => [
                [
                    'id' => 'abc123def4567890123456789012345678901234',
                    'message' => 'Test commit',
                ],
            ],
        ]);

        $validator = validator($request->all(), $request->rules());
        $this->assertFalse($validator->fails());
    }

    public function test_getter_methods_work(): void
    {
        $request = $this->createRequest([
            'X-GitHub-Event' => 'pull_request',
            'X-GitHub-Delivery' => '550e8400-e29b-41d4-a716-446655440000',
            'X-Hub-Signature-256' => 'sha256=abc123def456',
        ]);

        $this->assertEquals('pull_request', $request->getEventType());
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $request->getDeliveryId());
        $this->assertEquals('sha256=abc123def456', $request->getSignature());
    }
}