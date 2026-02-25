<?php

namespace App\Http\Resources; // Check this line for typos

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource // Check this class name
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
        ];
    }
}
