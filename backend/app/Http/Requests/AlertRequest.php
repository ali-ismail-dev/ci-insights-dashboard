<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alert Request
 *
 * Validates request data for alert operations.
 * Used by AlertController for filtering and updating alerts.
 *
 * @package App\Http\Requests
 */
class AlertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        // Authorization is handled by route middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index' => $this->indexRules(),
            'acknowledge' => $this->acknowledgeRules(),
            'resolve' => $this->resolveRules(),
            default => [],
        };
    }

    /**
     * Validation rules for index (list) operation
     */
    private function indexRules(): array
    {
        return [
            'repository_id' => 'nullable|integer|exists:repositories,id',
            'status' => 'nullable|string|in:active,acknowledged,resolved',
            'severity' => 'nullable|string|in:low,medium,high,critical',
            'alert_type' => 'nullable|string|max:100',
            'assigned_to_user_id' => 'nullable|integer|exists:users,id',
            'sort_by' => 'nullable|string|in:created_at,updated_at,severity,status',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Validation rules for acknowledge operation
     */
    private function acknowledgeRules(): array
    {
        return [
            // No additional validation needed for acknowledge
            // The alert ID is validated by route model binding
        ];
    }

    /**
     * Validation rules for resolve operation
     */
    private function resolveRules(): array
    {
        return [
            'resolution_notes' => 'nullable|string|max:1000',
            // The alert ID is validated by route model binding
        ];
    }

    /**
     * Get custom messages for validator errors
     */
    public function messages(): array
    {
        return [
            'repository_id.exists' => 'The specified repository does not exist',
            'status.in' => 'Status must be one of: active, acknowledged, resolved',
            'severity.in' => 'Severity must be one of: low, medium, high, critical',
            'sort_by.in' => 'Sort by must be one of: created_at, updated_at, severity, status',
            'sort_direction.in' => 'Sort direction must be one of: asc, desc',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page cannot exceed 100',
            'page.min' => 'Page must be at least 1',
            'resolution_notes.max' => 'Resolution notes cannot exceed 1000 characters',
        ];
    }

    /**
     * Get custom attributes for validator errors
     */
    public function attributes(): array
    {
        return [
            'repository_id' => 'repository ID',
            'status' => 'alert status',
            'severity' => 'alert severity',
            'alert_type' => 'alert type',
            'assigned_to_user_id' => 'assigned user ID',
            'sort_by' => 'sort field',
            'sort_direction' => 'sort direction',
            'per_page' => 'items per page',
            'page' => 'page number',
            'resolution_notes' => 'resolution notes',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set defaults for index operation
        if ($this->route()?->getActionMethod() === 'index') {
            if (!$this->has('sort_by')) {
                $this->merge(['sort_by' => 'created_at']);
            }

            if (!$this->has('sort_direction')) {
                $this->merge(['sort_direction' => 'desc']);
            }

            if (!$this->has('per_page')) {
                $this->merge(['per_page' => 20]);
            }

            if (!$this->has('status')) {
                $this->merge(['status' => 'active']);
            }
        }
    }

    /**
     * Get the validated data with defaults applied for index operation
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        if ($this->route()?->getActionMethod() === 'index') {
            return array_merge([
                'status' => 'active',
                'sort_by' => 'created_at',
                'sort_direction' => 'desc',
                'per_page' => 20,
                'page' => 1,
            ], $validated);
        }

        return $validated;
    }

    /**
     * Check if this is an index request
     */
    public function isIndexRequest(): bool
    {
        return $this->route()?->getActionMethod() === 'index';
    }

    /**
     * Check if this is an acknowledge request
     */
    public function isAcknowledgeRequest(): bool
    {
        return $this->route()?->getActionMethod() === 'acknowledge';
    }

    /**
     * Check if this is a resolve request
     */
    public function isResolveRequest(): bool
    {
        return $this->route()?->getActionMethod() === 'resolve';
    }
}