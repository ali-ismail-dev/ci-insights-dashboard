<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * Repository Model
 * 
 * Represents a GitHub/GitLab repository being tracked.
 * 
 * @property int $id
 * @property int $external_id
 * @property string $provider
 * @property string $full_name
 * @property string $name
 * @property string $owner
 * @property string $default_branch
 * @property string|null $description
 * @property string|null $language
 * @property string $html_url
 * @property string|null $clone_url
 * @property int $stars_count
 * @property int $forks_count
 * @property int $open_issues_count
 * @property string|null $webhook_secret
 * @property \Carbon\Carbon|null $webhook_verified_at
 * @property bool $ci_enabled
 * @property array|null $ci_config
 * @property bool $is_active
 * @property bool $is_private
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $last_synced_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Repository extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'external_id',
        'provider',
        'full_name',
        'name',
        'owner',
        'default_branch',
        'description',
        'language',
        'html_url',
        'clone_url',
        'stars_count',
        'forks_count',
        'open_issues_count',
        'webhook_secret',
        'webhook_verified_at',
        'ci_enabled',
        'ci_config',
        'is_active',
        'is_private',
        'metadata',
        'last_synced_at',
    ];

    protected $casts = [
        'external_id' => 'integer',
        'stars_count' => 'integer',
        'forks_count' => 'integer',
        'open_issues_count' => 'integer',
        'webhook_verified_at' => 'datetime',
        'ci_enabled' => 'boolean',
        'ci_config' => 'array',
        'is_active' => 'boolean',
        'is_private' => 'boolean',
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get searchable data for Scout
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'name' => $this->name,
            'owner' => $this->owner,
            'description' => $this->description,
            'language' => $this->language,
        ];
    }

    /**
     * Relations
     */
    public function pullRequests(): HasMany
    {
        return $this->hasMany(PullRequest::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function testRuns(): HasMany
    {
        return $this->hasMany(TestRun::class);
    }

    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRule::class);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(DailyMetric::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}