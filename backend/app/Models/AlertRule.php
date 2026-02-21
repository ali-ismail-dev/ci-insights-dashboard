<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alert Rule Model
 * 
 * @property int $id
 * @property int|null $repository_id
 * @property int $created_by_user_id
 * @property string $name
 * @property string|null $description
 * @property string $rule_type
 * @property array $conditions
 * @property string $severity
 * @property int $priority
 * @property array $notification_channels
 * @property array|null $notification_recipients
 * @property int $cooldown_minutes
 * @property int $max_alerts_per_day
 * @property string|null $message_template
 * @property array|null $schedule
 * @property bool $is_active
 * @property \Carbon\Carbon|null $last_evaluated_at
 * @property \Carbon\Carbon|null $last_triggered_at
 * @property int $trigger_count
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class AlertRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'repository_id',
        'created_by_user_id',
        'name',
        'description',
        'rule_type',
        'conditions',
        'severity',
        'priority',
        'notification_channels',
        'notification_recipients',
        'cooldown_minutes',
        'max_alerts_per_day',
        'message_template',
        'schedule',
        'is_active',
        'last_evaluated_at',
        'last_triggered_at',
        'trigger_count',
        'metadata',
    ];

    protected $casts = [
        'repository_id' => 'integer',
        'created_by_user_id' => 'integer',
        'conditions' => 'array',
        'priority' => 'integer',
        'notification_channels' => 'array',
        'notification_recipients' => 'array',
        'cooldown_minutes' => 'integer',
        'max_alerts_per_day' => 'integer',
        'schedule' => 'array',
        'is_active' => 'boolean',
        'last_evaluated_at' => 'datetime',
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Relations
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('rule_type', $type);
    }

    public function scopeDueForEvaluation(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('last_evaluated_at')
                    ->orWhere('last_evaluated_at', '<=', now()->subMinutes(5));
            });
    }

    /**
     * Check if rule is in cooldown period
     */
    public function isInCooldown(): bool
    {
        if (!$this->last_triggered_at) {
            return false;
        }

        return $this->last_triggered_at->addMinutes($this->cooldown_minutes)->isFuture();
    }
}