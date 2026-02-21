<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepositoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'provider' => $this->provider,
            'full_name' => $this->full_name,
            'name' => $this->name,
            'owner' => $this->owner,
            'default_branch' => $this->default_branch,
            'description' => $this->description,
            'language' => $this->language,
            'html_url' => $this->html_url,
            'stars_count' => $this->stars_count,
            'forks_count' => $this->forks_count,
            'open_issues_count' => $this->open_issues_count,
            'ci_enabled' => $this->ci_enabled,
            'is_active' => $this->is_active,
            'is_private' => $this->is_private,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            
            // Counts (if loaded)
            'pull_requests_count' => $this->whenCounted('pullRequests'),
            'open_prs_count' => $this->whenCounted('pullRequests as open_prs_count'),
            'test_runs_count' => $this->whenCounted('testRuns'),
        ];
    }
}