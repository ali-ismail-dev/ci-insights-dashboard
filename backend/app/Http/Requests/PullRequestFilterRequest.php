<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pull Request Filter Request
 *
 * Validates query parameters for filtering pull requests.
 * Used by PullRequestController@index
 *
 * @package App\Http\Requests
 */
class PullRequestFilterRequest extends FormRequest
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
        return [
            'state' => 'nullable|string|in:all,open,closed,merged',
            'ci_status' => 'nullable|string|in:all,success,failure,pending,cancelled,skipped',
            'is_stale' => 'nullable|boolean',
            'is_draft' => 'nullable|boolean',
            'author_id' => 'nullable|integer|exists:users,id',
            'label' => 'nullable|string|max:100',
            'sort_by' => 'nullable|string|in:created_at,updated_at,merged_at,cycle_time',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Get custom messages for validator errors
     */
    public function messages(): array
    {
        return [
            'state.in' => 'State must be one of: all, open, closed, merged',
            'ci_status.in' => 'CI status must be one of: all, success, failure, pending, cancelled, skipped',
            'sort_by.in' => 'Sort by must be one of: created_at, updated_at, merged_at, cycle_time',
            'sort_direction.in' => 'Sort direction must be one of: asc, desc',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page cannot exceed 100',
            'page.min' => 'Page must be at least 1',
        ];
    }

    /**
     * Get custom attributes for validator errors
     */
    public function attributes(): array
    {
        return [
            'state' => 'pull request state',
            'ci_status' => 'CI status',
            'is_stale' => 'stale flag',
            'is_draft' => 'draft flag',
            'author_id' => 'author ID',
            'label' => 'label name',
            'sort_by' => 'sort field',
            'sort_direction' => 'sort direction',
            'per_page' => 'items per page',
            'page' => 'page number',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Convert string booleans to actual booleans
        if ($this->has('is_stale')) {
            $this->merge([
                'is_stale' => $this->boolean('is_stale'),
            ]);
        }

        if ($this->has('is_draft')) {
            $this->merge([
                'is_draft' => $this->boolean('is_draft'),
            ]);
        }

        // Set defaults
        if (!$this->has('sort_by')) {
            $this->merge(['sort_by' => 'created_at']);
        }

        if (!$this->has('sort_direction')) {
            $this->merge(['sort_direction' => 'desc']);
        }

        if (!$this->has('per_page')) {
            $this->merge(['per_page' => 20]);
        }
    }

    /**
     * Get the validated data with defaults applied
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        return array_merge([
            'state' => 'all',
            'ci_status' => 'all',
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
            'per_page' => 20,
            'page' => 1,
        ], $validated);
    }
}