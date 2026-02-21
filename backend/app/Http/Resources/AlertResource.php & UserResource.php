<?php
// app/Http/Resources/AlertResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'alert_type' => $this->alert_type,
            'severity' => $this->severity,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->status,
            'context' => $this->context,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

// app/Http/Resources/UserResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
        ];
    }
}