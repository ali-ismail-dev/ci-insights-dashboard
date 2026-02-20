<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook Controller
 * 
 * Handles incoming webhooks from GitHub/GitLab with signature verification.
 * Implements idempotency via delivery_id and queues processing jobs.
 * 
 * PERFORMANCE TARGET: < 100ms response time (GitHub timeout: 10 seconds)
 * 
 * @package App\Http\Controllers\Api
 */
class WebhookController extends Controller
{
    /**
     * GitHub webhook endpoint
     * 
     * POST /webhooks/github
     * 
     * Headers:
     *   X-GitHub-Event: pull_request, push, check_run, etc.
     *   X-GitHub-Delivery: unique-delivery-id-12345
     *   X-Hub-Signature-256: sha256=abc123...
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function github(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        // Extract headers
        $eventType = $request->header('X-GitHub-Event');
        $deliveryId = $request->header('X-GitHub-Delivery');
        $signature = $request->header('X-Hub-Signature-256');
        
        // Validate required headers
        if (!$eventType || !$deliveryId || !$signature) {
            Log::warning('GitHub webhook missing required headers', [
                'has_event' => (bool) $eventType,
                'has_delivery' => (bool) $deliveryId,
                'has_signature' => (bool) $signature,
            ]);
            
            return response()->json([
                'error' => 'Missing required headers',
                'message' => 'X-GitHub-Event, X-GitHub-Delivery, and X-Hub-Signature-256 are required',
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Verify webhook signature (CRITICAL SECURITY CHECK)
        if (!$this->verifyGitHubSignature($request, $signature)) {
            Log::error('GitHub webhook signature verification failed', [
                'delivery_id' => $deliveryId,
                'event_type' => $eventType,
                'source_ip' => $request->ip(),
            ]);
            
            return response()->json([
                'error' => 'Invalid signature',
                'message' => 'Webhook signature verification failed',
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Check for duplicate delivery (idempotency)
        $existingEvent = WebhookEvent::where('delivery_id', $deliveryId)->first();
        
        if ($existingEvent) {
            Log::info('Duplicate webhook delivery received', [
                'delivery_id' => $deliveryId,
                'event_type' => $eventType,
                'original_created_at' => $existingEvent->created_at,
            ]);
            
            return response()->json([
                'status' => 'duplicate',
                'message' => 'Webhook already processed',
                'event_id' => $existingEvent->id,
            ], Response::HTTP_OK);
        }
        
        // Extract action from payload (if present)
        $payload = $request->all();
        $action = $payload['action'] ?? null;
        
        // Determine repository ID from payload
        $repositoryId = $this->extractRepositoryId($payload);
        
        // Store webhook event (ACID transaction)
        try {
            $event = WebhookEvent::create([
                'repository_id' => $repositoryId,
                'provider' => 'github',
                'event_type' => $eventType,
                'action' => $action,
                'delivery_id' => $deliveryId,
                'signature' => $signature,
                'signature_verified' => true,
                'verified_at' => now(),
                'payload' => $payload,
                'status' => 'pending',
                'source_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'headers' => [
                    'X-GitHub-Event' => $eventType,
                    'X-GitHub-Delivery' => $deliveryId,
                    'X-GitHub-Hook-ID' => $request->header('X-GitHub-Hook-ID'),
                    'X-GitHub-Hook-Installation-Target-ID' => $request->header('X-GitHub-Hook-Installation-Target-ID'),
                ],
            ]);
            
            Log::info('Webhook event stored successfully', [
                'event_id' => $event->id,
                'delivery_id' => $deliveryId,
                'event_type' => $eventType,
                'action' => $action,
                'repository_id' => $repositoryId,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Failed to store webhook event', [
                'delivery_id' => $deliveryId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Storage failed',
                'message' => 'Failed to store webhook event',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
        // Determine if event should be processed
        if ($this->shouldProcessEvent($eventType, $action)) {
            // Queue processing job (high priority for user-facing events)
            $queue = $this->determineQueuePriority($eventType, $action);
            
            ProcessWebhookJob::dispatch($event)->onQueue($queue);
            
            Log::info('Webhook processing job dispatched', [
                'event_id' => $event->id,
                'queue' => $queue,
            ]);
        } else {
            // Event type not supported, mark as skipped
            $event->update([
                'status' => 'skipped',
                'processed_at' => now(),
            ]);
            
            Log::debug('Webhook event skipped (not supported)', [
                'event_id' => $event->id,
                'event_type' => $eventType,
                'action' => $action,
            ]);
        }
        
        // Calculate response time
        $responseTime = (microtime(true) - $startTime) * 1000; // milliseconds
        
        // Log slow responses (> 100ms target)
        if ($responseTime > 100) {
            Log::warning('Slow webhook response', [
                'delivery_id' => $deliveryId,
                'response_time_ms' => round($responseTime, 2),
            ]);
        }
        
        // Return 202 Accepted (GitHub expects this for async processing)
        return response()->json([
            'status' => 'accepted',
            'message' => 'Webhook received and queued for processing',
            'event_id' => $event->id,
            'delivery_id' => $deliveryId,
            'response_time_ms' => round($responseTime, 2),
        ], Response::HTTP_ACCEPTED);
    }
    
    /**
     * Verify GitHub webhook signature using HMAC-SHA256
     * 
     * @param Request $request
     * @param string $signature Header value (sha256=abc123...)
     * @return bool
     */
    private function verifyGitHubSignature(Request $request, string $signature): bool
    {
        // Get webhook secret from config (per repository or global)
        $secret = config('services.github.webhook_secret');
        
        if (empty($secret)) {
            Log::critical('GitHub webhook secret not configured');
            return false;
        }
        
        // Get raw request body (important: before any parsing)
        $payload = $request->getContent();
        
        // Calculate expected signature
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        
        // Constant-time comparison (prevents timing attacks)
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Extract repository ID from webhook payload
     * 
     * @param array $payload
     * @return int|null
     */
    private function extractRepositoryId(array $payload): ?int
    {
        // GitHub webhook structure
        if (isset($payload['repository']['id'])) {
            return (int) $payload['repository']['id'];
        }
        
        // Organization-level events might not have repository
        return null;
    }
    
    /**
     * Determine if event should be processed
     * 
     * @param string $eventType
     * @param string|null $action
     * @return bool
     */
    private function shouldProcessEvent(string $eventType, ?string $action): bool
    {
        // Supported event types and actions
        $supportedEvents = [
            'pull_request' => ['opened', 'closed', 'reopened', 'synchronize', 'edited'],
            'pull_request_review' => ['submitted', 'edited', 'dismissed'],
            'pull_request_review_comment' => ['created', 'edited', 'deleted'],
            'check_run' => ['completed', 'rerequested'],
            'check_suite' => ['completed'],
            'status' => null, // All status events
            'push' => null, // All push events
            'workflow_run' => ['completed'],
        ];
        
        // Check if event type is supported
        if (!array_key_exists($eventType, $supportedEvents)) {
            return false;
        }
        
        // If no action filter specified, process all actions
        $allowedActions = $supportedEvents[$eventType];
        if ($allowedActions === null) {
            return true;
        }
        
        // Check if action is in allowed list
        return in_array($action, $allowedActions, true);
    }
    
    /**
     * Determine queue priority based on event type and action
     * 
     * @param string $eventType
     * @param string|null $action
     * @return string Queue name (high, default, low)
     */
    private function determineQueuePriority(string $eventType, ?string $action): string
    {
        // High priority: User-facing real-time events
        $highPriorityEvents = [
            'pull_request' => ['opened', 'reopened', 'closed'],
            'pull_request_review' => ['submitted'],
        ];
        
        // Check if high priority
        if (isset($highPriorityEvents[$eventType])) {
            $allowedActions = $highPriorityEvents[$eventType];
            if (in_array($action, $allowedActions, true)) {
                return 'high';
            }
        }
        
        // Low priority: Background analysis
        $lowPriorityEvents = [
            'push',
            'check_suite',
        ];
        
        if (in_array($eventType, $lowPriorityEvents, true)) {
            return 'low';
        }
        
        // Default priority: Everything else
        return 'default';
    }
    
    /**
     * GitLab webhook endpoint (future implementation)
     * 
     * POST /webhooks/gitlab
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function gitlab(Request $request): JsonResponse
    {
        return response()->json([
            'error' => 'Not implemented',
            'message' => 'GitLab webhooks are not yet supported',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
    
    /**
     * Webhook test endpoint (dev/staging only)
     * 
     * POST /webhooks/test
     * 
     * Accepts any payload without signature verification for testing.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function test(Request $request): JsonResponse
    {
        // Only allow in non-production environments
        if (app()->environment('production')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Test endpoint not available in production',
            ], Response::HTTP_FORBIDDEN);
        }
        
        // Store test event
        $event = WebhookEvent::create([
            'repository_id' => $request->input('repository_id', 1),
            'provider' => 'test',
            'event_type' => $request->input('event_type', 'test_event'),
            'action' => $request->input('action'),
            'delivery_id' => 'test-' . uniqid(),
            'signature' => 'test-signature',
            'signature_verified' => true,
            'verified_at' => now(),
            'payload' => $request->all(),
            'status' => 'pending',
        ]);
        
        // Queue processing
        ProcessWebhookJob::dispatch($event)->onQueue('default');
        
        return response()->json([
            'status' => 'accepted',
            'event_id' => $event->id,
            'message' => 'Test webhook queued',
        ], Response::HTTP_ACCEPTED);
    }
}