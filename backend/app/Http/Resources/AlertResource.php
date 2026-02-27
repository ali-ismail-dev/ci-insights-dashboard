<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Alert Resource
 *
 * Transforms Alert model into API-friendly JSON.
 */
class AlertResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alert_rule_id' => $this->alert_rule_id,
            'repository_id' => $this->repository_id,
            'pull_request_id' => $this->pull_request_id,
            'alert_type' => $this->alert_type,
            'severity' => $this->severity,
            'title' => $this->title,
            'message' => $this->message,
            'context' => $this->context,
            'status' => $this->status,
            'acknowledged_at' => $this->acknowledged_at,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}


