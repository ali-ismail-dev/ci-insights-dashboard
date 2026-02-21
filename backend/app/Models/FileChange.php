<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * File Change Model
 * 
 * @property int $id
 * @property int $repository_id
 * @property int $pull_request_id
 * @property string $file_path
 * @property string|null $file_extension
 * @property string|null $directory
 * @property string $change_type
 * @property int $additions
 * @property int $deletions
 * @property int $changes
 * @property string|null $previous_file_path
 * @property bool $caused_ci_failure
 * @property int $ci_failure_count
 * @property float|null $failure_rate
 * @property int $total_changes_count
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FileChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'pull_request_id',
        'file_path',
        'file_extension',
        'directory',
        'change_type',
        'additions',
        'deletions',
        'changes',
        'previous_file_path',
        'caused_ci_failure',
        'ci_failure_count',
        'failure_rate',
        'total_changes_count',
        'metadata',
    ];

    protected $casts = [
        'repository_id' => 'integer',
        'pull_request_id' => 'integer',
        'additions' => 'integer',
        'deletions' => 'integer',
        'changes' => 'integer',
        'caused_ci_failure' => 'boolean',
        'ci_failure_count' => 'integer',
        'failure_rate' => 'decimal:2',
        'total_changes_count' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Relations
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * Scopes
     */
    public function scopeCausedFailure(Builder $query): Builder
    {
        return $query->where('caused_ci_failure', true);
    }

    public function scopeHighFailureRate(Builder $query, int $threshold = 50): Builder
    {
        return $query->where('failure_rate', '>=', $threshold);
    }

    public function scopeByExtension(Builder $query, string $extension): Builder
    {
        return $query->where('file_extension', $extension);
    }

    public function scopeByDirectory(Builder $query, string $directory): Builder
    {
        return $query->where('directory', 'like', $directory . '%');
    }
}