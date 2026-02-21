<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alert Model
 * 
 * @property int $id
 * @property int $alert_rule_id
 * @property int|null $repository_id
 * @property int|null $pull_request_id
 * @property string $alert_type
 * @property string $severity
 * @property string $title
 * @property string $message
 * @property array|null $context
 * @property string $status
 * @property \Carbon\Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by_user_id
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null $resolved_by_user_id
 * @property string|null $resolution_notes
 * @property array|null $notification_channels
 * @property array|null $notification_status
 * @property \Carbon\Carbon|null $notified_at
 * @property string|null $fingerprint
 * @property int|null $parent_alert_id
 * @property int $occurrence_count
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_rule_id',
        'repository_id',
        'pull_request_id',
        'alert_type',
        'severity',
        'title',
        'message',
        'context',
        'status',
        'acknowledged_at',
        'acknowledged_by_user_id',
        'resolved_at',
        'resolved_by_user_id',
        'resolution_notes',
        'notification_channels',
        'notification_status',
        'notified_at',
        'fingerprint',
        'parent_alert_id',
        'occurrence_count',
        'metadata',
    ];

    protected $casts = [
        'alert_rule_id' => 'integer',
        'repository_id' => 'integer',
        'pull_request_id' => 'integer',
        'context' => 'array',
        'acknowledged_at' => 'datetime',
        'acknowledged_by_user_id' => 'integer',
        'resolved_at' => 'datetime',
        'resolved_by_user_id' => 'integer',
        'notification_channels' => 'array',
        'notification_status' => 'array',
        'notified_at' => 'datetime',
        'parent_alert_id' => 'integer',
        'occurrence_count' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Relations
     */
    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function parentAlert(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'parent_alert_id');
    }

    /**
     * Scopes
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeAcknowledged(Builder $query): Builder
    {
        return $query->where('status', 'acknowledged');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}