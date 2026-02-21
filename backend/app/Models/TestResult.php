<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Test Result Model
 * 
 * @property int $id
 * @property int $test_run_id
 * @property int $repository_id
 * @property string $test_identifier
 * @property string $test_name
 * @property string $test_file
 * @property string|null $test_class
 * @property string|null $test_method
 * @property string $status
 * @property int|null $duration
 * @property string|null $error_message
 * @property string|null $stack_trace
 * @property string|null $failure_type
 * @property bool $is_flaky
 * @property bool $passed_on_retry
 * @property int $retry_count
 * @property float|null $flakiness_score
 * @property int|null $failure_rate
 * @property int|null $assertions_count
 * @property array|null $tags
 * @property array|null $metadata
 * @property \Carbon\Carbon $executed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_run_id',
        'repository_id',
        'test_identifier',
        'test_name',
        'test_file',
        'test_class',
        'test_method',
        'status',
        'duration',
        'error_message',
        'stack_trace',
        'failure_type',
        'is_flaky',
        'passed_on_retry',
        'retry_count',
        'flakiness_score',
        'failure_rate',
        'assertions_count',
        'tags',
        'metadata',
        'executed_at',
    ];

    protected $casts = [
        'test_run_id' => 'integer',
        'repository_id' => 'integer',
        'duration' => 'integer',
        'is_flaky' => 'boolean',
        'passed_on_retry' => 'boolean',
        'retry_count' => 'integer',
        'flakiness_score' => 'decimal:2',
        'failure_rate' => 'integer',
        'assertions_count' => 'integer',
        'tags' => 'array',
        'metadata' => 'array',
        'executed_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function testRun(): BelongsTo
    {
        return $this->belongsTo(TestRun::class);
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Scopes
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeFlaky(Builder $query): Builder
    {
        return $query->where('is_flaky', true);
    }

    public function scopeHighFlakiness(Builder $query, int $threshold = 30): Builder
    {
        return $query->where('flakiness_score', '>=', $threshold);
    }

    public function scopeByTest(Builder $query, string $identifier): Builder
    {
        return $query->where('test_identifier', $identifier);
    }
}