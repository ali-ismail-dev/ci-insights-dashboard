<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Webhook Event Model
 * 
 * @property int $id
 * @property int|null $repository_id
 * @property string $provider
 * @property string $event_type
 * @property string|null $action
 * @property string $delivery_id
 * @property string $signature
 * @property bool $signature_verified
 * @property \Carbon\Carbon|null $verified_at
 * @property array $payload
 * @property string $status
 * @property \Carbon\Carbon|null $processed_at
 * @property int|null $processing_duration
 * @property string|null $error_message
 * @property int $retry_count
 * @property \Carbon\Carbon|null $retry_after
 * @property string|null $source_ip
 * @property string|null $user_agent
 * @property array|null $headers
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'provider',
        'event_type',
        'action',
        'delivery_id',
        'signature',
        'signature_verified',
        'verified_at',
        'payload',
        'status',
        'processed_at',
        'processing_duration',
        'error_message',
        'retry_count',
        'retry_after',
        'source_ip',
        'user_agent',
        'headers',
    ];

    protected $casts = [
        'repository_id' => 'integer',
        'signature_verified' => 'boolean',
        'verified_at' => 'datetime',
        'payload' => 'array',
        'processed_at' => 'datetime',
        'processing_duration' => 'integer',
        'retry_count' => 'integer',
        'retry_after' => 'datetime',
        'headers' => 'array',
    ];

    /**
     * Relations
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Scopes
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeForRetry(Builder $query): Builder
    {
        return $query->where('status', 'failed')
            ->where('retry_count', '<', 3)
            ->where(function ($q) {
                $q->whereNull('retry_after')
                    ->orWhere('retry_after', '<=', now());
            });
    }
}