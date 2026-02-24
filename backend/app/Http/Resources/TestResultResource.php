<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'test_run_id' => $this->test_run_id,
            'test_name' => $this->test_name,
            'test_file' => $this->test_file,
            'test_class' => $this->test_class,
            'test_method' => $this->test_method,
            'status' => $this->status,
            'duration' => $this->duration,
            'error_message' => $this->error_message,
            'stack_trace' => $this->stack_trace,
            'is_flaky' => $this->is_flaky,
            'flakiness_score' => $this->flakiness_score,
            'executed_at' => $this->executed_at?->toIso8601String(),
        ];
    }
}