<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * Pull Request Model
 * 
 * @property int $id
 * @property int $repository_id
 * @property int|null $author_id
 * @property int $external_id
 * @property int $number
 * @property string $state
 * @property string $title
 * @property string|null $description
 * @property string $head_branch
 * @property string $base_branch
 * @property string $head_sha
 * @property string $base_sha
 * @property string $html_url
 * @property int $additions
 * @property int $deletions
 * @property int $changed_files
 * @property int $commits_count
 * @property int $comments_count
 * @property string|null $review_status
 * @property int $approvals_count
 * @property string|null $ci_status
 * @property int $ci_checks_count
 * @property int $ci_checks_passed
 * @property int $ci_checks_failed
 * @property float|null $test_coverage
 * @property int $tests_total
 * @property int $tests_passed
 * @property int $tests_failed
 * @property int|null $cycle_time
 * @property int|null $time_to_first_review
 * @property bool $is_draft
 * @property bool $is_stale
 * @property array $labels
 * @property array $assignees
 * @property \Carbon\Carbon|null $merged_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PullRequest extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'repository_id',
        'author_id',
        'external_id',
        'number',
        'state',
        'title',
        'description',
        'head_branch',
        'base_branch',
        'head_sha',
        'base_sha',
        'html_url',
        'diff_url',
        'additions',
        'deletions',
        'changed_files',
        'commits_count',
        'comments_count',
        'review_status',
        'approvals_count',
        'review_comments_count',
        'ci_status',
        'ci_checks_count',
        'ci_checks_passed',
        'ci_checks_failed',
        'test_coverage',
        'tests_total',
        'tests_passed',
        'tests_failed',
        'tests_skipped',
        'cycle_time',
        'time_to_first_review',
        'time_to_approval',
        'time_to_merge',
        'is_draft',
        'is_mergeable',
        'is_hot',
        'is_stale',
        'labels',
        'assignees',
        'requested_reviewers',
        'metadata',
        'first_commit_at',
        'first_review_at',
        'approved_at',
        'merged_at',
        'closed_at',
        'last_activity_at',
    ];

    protected $casts = [
        'repository_id' => 'integer',
        'author_id' => 'integer',
        'external_id' => 'integer',
        'number' => 'integer',
        'additions' => 'integer',
        'deletions' => 'integer',
        'changed_files' => 'integer',
        'commits_count' => 'integer',
        'comments_count' => 'integer',
        'approvals_count' => 'integer',
        'ci_checks_count' => 'integer',
        'ci_checks_passed' => 'integer',
        'ci_checks_failed' => 'integer',
        'test_coverage' => 'decimal:2',
        'tests_total' => 'integer',
        'tests_passed' => 'integer',
        'tests_failed' => 'integer',
        'cycle_time' => 'integer',
        'time_to_first_review' => 'integer',
        'is_draft' => 'boolean',
        'is_mergeable' => 'boolean',
        'is_hot' => 'boolean',
        'is_stale' => 'boolean',
        'labels' => 'array',
        'assignees' => 'array',
        'requested_reviewers' => 'array',
        'metadata' => 'array',
        'first_commit_at' => 'datetime',
        'first_review_at' => 'datetime',
        'approved_at' => 'datetime',
        'merged_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function testRuns(): HasMany
    {
        return $this->hasMany(TestRun::class);
    }

    public function fileChanges(): HasMany
    {
        return $this->hasMany(FileChange::class);
    }

    /**
     * Scopes
     */
    public function scopeOpen($query)
    {
        return $query->where('state', 'open');
    }

    public function scopeMerged($query)
    {
        return $query->where('state', 'merged');
    }

    public function scopeStale($query)
    {
        return $query->where('is_stale', true);
    }

    public function scopeForRepository($query, int $repositoryId)
    {
        return $query->where('repository_id', $repositoryId);
    }

    /**
     * Searchable
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'description' => $this->description,
            'state' => $this->state,
            'author' => $this->author?->name,
        ];
    }
}