<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Webhook Request
 *
 * Validates incoming webhook payloads from GitHub/GitLab.
 * Used by WebhookController for webhook endpoints.
 *
 * @package App\Http\Requests
 */
class WebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        // Webhooks are public endpoints, authorization is handled by signature verification
        return true;
    }

    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            // GitHub webhook headers (validated in controller, but defined here for completeness)
            'X-GitHub-Event' => 'required|string|max:50',
            'X-GitHub-Delivery' => 'required|string|uuid',
            'X-Hub-Signature-256' => 'required|string|regex:/^sha256=[a-f0-9]{64}$/',

            // Payload structure validation
            'action' => 'nullable|string|max:50',
            'repository.id' => 'required|integer',
            'repository.full_name' => 'required|string|max:200',
            'repository.html_url' => 'required|url',

            // Pull request specific validation (when event type is pull_request)
            'pull_request' => 'required_if:X-GitHub-Event,pull_request|array',
            'pull_request.id' => 'required_if:X-GitHub-Event,pull_request|integer',
            'pull_request.number' => 'required_if:X-GitHub-Event,pull_request|integer|min:1',
            'pull_request.state' => 'required_if:X-GitHub-Event,pull_request|string|in:open,closed',
            'pull_request.title' => 'required_if:X-GitHub-Event,pull_request|string|max:500',
            'pull_request.body' => 'nullable|string',
            'pull_request.html_url' => 'required_if:X-GitHub-Event,pull_request|url',
            'pull_request.user.id' => 'required_if:X-GitHub-Event,pull_request|integer',
            'pull_request.user.login' => 'required_if:X-GitHub-Event,pull_request|string|max:100',

            // Check run specific validation (when event type is check_run)
            'check_run' => 'required_if:X-GitHub-Event,check_run|array',
            'check_run.id' => 'required_if:X-GitHub-Event,check_run|integer',
            'check_run.name' => 'required_if:X-GitHub-Event,check_run|string|max:200',
            'check_run.status' => 'required_if:X-GitHub-Event,check_run|string|in:completed,in_progress,queued',
            'check_run.conclusion' => 'nullable|string|in:success,failure,neutral,cancelled,skipped,timed_out,action_required',

            // Status event validation (when event type is status)
            'sha' => 'required_if:X-GitHub-Event,status|string|regex:/^[a-f0-9]{40}$/',
            'state' => 'required_if:X-GitHub-Event,status|string|in:pending,success,failure,error',

            // Push event validation (when event type is push)
            'ref' => 'required_if:X-GitHub-Event,push|string|max:200',
            'before' => 'required_if:X-GitHub-Event,push|string|regex:/^[a-f0-9]{40}$/',
            'after' => 'required_if:X-GitHub-Event,push|string|regex:/^[a-f0-9]{40}$/',
            'commits' => 'required_if:X-GitHub-Event,push|array',
            'commits.*.id' => 'required_if:X-GitHub-Event,push|string|regex:/^[a-f0-9]{40}$/',
        ];
    }

    /**
     * Get custom messages for validator errors
     */
    public function messages(): array
    {
        return [
            'X-GitHub-Event.required' => 'GitHub event header is required',
            'X-GitHub-Delivery.required' => 'GitHub delivery header is required',
            'X-GitHub-Delivery.uuid' => 'GitHub delivery must be a valid UUID',
            'X-Hub-Signature-256.required' => 'GitHub signature header is required',
            'X-Hub-Signature-256.regex' => 'GitHub signature must be a valid SHA256 hash',
            'repository.id.required' => 'Repository ID is required in payload',
            'pull_request.required_if' => 'Pull request data is required for pull_request events',
            'check_run.required_if' => 'Check run data is required for check_run events',
            'sha.required_if' => 'Commit SHA is required for status events',
            'ref.required_if' => 'Git reference is required for push events',
        ];
    }

    /**
     * Get custom attributes for validator errors
     */
    public function attributes(): array
    {
        return [
            'X-GitHub-Event' => 'GitHub event type',
            'X-GitHub-Delivery' => 'GitHub delivery ID',
            'X-Hub-Signature-256' => 'GitHub webhook signature',
            'repository.id' => 'repository ID',
            'repository.full_name' => 'repository full name',
            'pull_request.id' => 'pull request ID',
            'pull_request.number' => 'pull request number',
            'pull_request.state' => 'pull request state',
            'pull_request.title' => 'pull request title',
            'check_run.id' => 'check run ID',
            'check_run.name' => 'check run name',
            'check_run.status' => 'check run status',
            'check_run.conclusion' => 'check run conclusion',
            'sha' => 'commit SHA',
            'state' => 'commit state',
            'ref' => 'git reference',
            'before' => 'before SHA',
            'after' => 'after SHA',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $eventType = $this->header('X-GitHub-Event');

            // Additional validation based on event type
            if ($eventType === 'pull_request') {
                $this->validatePullRequestEvent($validator);
            } elseif ($eventType === 'check_run') {
                $this->validateCheckRunEvent($validator);
            }
        });
    }

    /**
     * Additional validation for pull_request events
     */
    private function validatePullRequestEvent($validator): void
    {
        $prData = $this->input('pull_request', []);

        // Validate that required PR fields are present
        if (empty($prData['head']['ref']) || empty($prData['base']['ref'])) {
            $validator->errors()->add('pull_request', 'Pull request must have head and base branch information');
        }

        // Validate mergeable state for closed PRs
        if ($prData['state'] === 'closed' && !array_key_exists('merged', $prData)) {
            $validator->errors()->add('pull_request', 'Closed pull requests must specify merged status');
        }
    }

    /**
     * Additional validation for check_run events
     */
    private function validateCheckRunEvent($validator): void
    {
        $checkRunData = $this->input('check_run', []);

        // If status is completed, conclusion is required
        if (($checkRunData['status'] ?? null) === 'completed' && empty($checkRunData['conclusion'])) {
            $validator->errors()->add('check_run.conclusion', 'Conclusion is required when check run status is completed');
        }

        // If status is not completed, conclusion should not be present
        if (($checkRunData['status'] ?? null) !== 'completed' && !empty($checkRunData['conclusion'])) {
            $validator->errors()->add('check_run.conclusion', 'Conclusion should not be present when check run status is not completed');
        }
    }

    /**
     * Get the event type from headers
     */
    public function getEventType(): string
    {
        return $this->header('X-GitHub-Event', '');
    }

    /**
     * Get the delivery ID from headers
     */
    public function getDeliveryId(): string
    {
        return $this->header('X-GitHub-Delivery', '');
    }

    /**
     * Get the webhook signature from headers
     */
    public function getSignature(): string
    {
        return $this->header('X-Hub-Signature-256', '');
    }
}