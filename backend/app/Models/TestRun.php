<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Test Run Model
 * 
 * @property int $id
 * @property int $repository_id
 * @property int|null $pull_request_id
 * @property string $ci_provider
 * @property string $external_id
 * @property string $workflow_name
 * @property string|null $job_name
 * @property string $branch
 * @property string $commit_sha
 * @property string $status
 * @property int $total_tests
 * @property int $passed_tests
 * @property int $failed_tests
 * @property int $skipped_tests
 * @property int $flaky_tests
 * @property float|null $line_coverage
 * @property float|null $branch_coverage
 * @property float|null $method_coverage
 * @property int|null $duration
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property array|null $failed_tests_details
 * @property array|null $flaky_tests_details
 * @property array|null $coverage_report
 * @property string|null $run_url
 * @property string|null $logs_url
 * @property array|null $environment
 * @property bool $is_retry
 * @property int $retry_attempt
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TestRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'pull_request_id',
        'ci_provider',
        'external_id',
        'workflow_name',
        'job_name',
        'branch',
        'commit_sha',
        'status',
        'total_tests',
        'passed_tests',
        'failed_tests',
        'skipped_tests',
        'flaky_tests',
        'line_coverage',
        'branch_coverage',
        'method_coverage',
        'duration',
        'started_at',
        'completed_at',
        'failed_tests_details',
        'flaky_tests_details',
        'coverage_report',
        'run_url',
        'logs_url',
        'environment',
        'is_retry',
        'retry_attempt',
    ];

    protected $casts = [
        'repository_id' => 'integer',
        'pull_request_id' => 'integer',
        'total_tests' => 'integer',
        'passed_tests' => 'integer',
        'failed_tests' => 'integer',
        'skipped_tests' => 'integer',
        'flaky_tests' => 'integer',
        'line_coverage' => 'decimal:2',
        'branch_coverage' => 'decimal:2',
        'method_coverage' => 'decimal:2',
        'duration' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_tests_details' => 'array',
        'flaky_tests_details' => 'array',
        'coverage_report' => 'array',
        'environment' => 'array',
        'is_retry' => 'boolean',
        'retry_attempt' => 'integer',
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

    public function testResults(): HasMany
    {
        return $this->hasMany(TestResult::class);
    }

    /**
     * Scopes
     */
    public function scopeSuccess(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failure');
    }

    public function scopeWithFlaky(Builder $query): Builder
    {
        return $query->where('flaky_tests', '>', 0);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }
}