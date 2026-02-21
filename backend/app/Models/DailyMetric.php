<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daily Metric Model
 * 
 * Pre-aggregated daily metrics for fast dashboard queries.
 * 
 * @property int $id
 * @property int $repository_id
 * @property string $metric_date
 * @property int $prs_opened
 * @property int $prs_merged
 * @property int $prs_closed
 * @property int $prs_active
 * @property float|null $avg_cycle_time
 * @property float|null $median_cycle_time
 * @property float|null $avg_time_to_first_review
 * @property float|null $avg_time_to_merge
 * @property int $test_runs_total
 * @property int $test_runs_passed
 * @property int $test_runs_failed
 * @property float|null $ci_success_rate
 * @property float|null $avg_test_duration
 * @property float|null $avg_line_coverage
 * @property float|null $avg_branch_coverage
 * @property float|null $coverage_trend
 * @property int $flaky_tests_detected
 * @property int $flaky_tests_fixed
 * @property float|null $avg_flakiness_score
 * @property int $active_contributors
 * @property int $total_commits
 * @property int $total_code_changes
 * @property int $alerts_triggered
 * @property int $alerts_resolved
 * @property bool $is_final
 * @property \Carbon\Carbon|null $calculated_at
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DailyMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'metric_date',
        'prs_opened',
        'prs_merged',
        'prs_closed',
        'prs_active',
        'avg_cycle_time',
        'median_cycle_time',
        'avg_time_to_first_review',
        'avg_time_to_merge',
        'test_runs_total',
        'test_runs_passed',
        'test_runs_failed',
        'ci_success_rate',
        'avg_test_duration',
        'avg_line_coverage',
        'avg_branch_coverage',
        'coverage_trend',
        'flaky_tests_detected',
        'flaky_tests_fixed',
        'avg_flakiness_score',
        'active_contributors',
        'total_commits',
        'total_code_changes',
        'alerts_triggered',
        'alerts_resolved',
        'is_final',
        'calculated_at',
        'metadata',
    ];

    protected $casts = [
        'repository_id' => 'integer',
        'metric_date' => 'date',
        'prs_opened' => 'integer',
        'prs_merged' => 'integer',
        'prs_closed' => 'integer',
        'prs_active' => 'integer',
        'avg_cycle_time' => 'decimal:2',
        'median_cycle_time' => 'decimal:2',
        'avg_time_to_first_review' => 'decimal:2',
        'avg_time_to_merge' => 'decimal:2',
        'test_runs_total' => 'integer',
        'test_runs_passed' => 'integer',
        'test_runs_failed' => 'integer',
        'ci_success_rate' => 'decimal:2',
        'avg_test_duration' => 'decimal:2',
        'avg_line_coverage' => 'decimal:2',
        'avg_branch_coverage' => 'decimal:2',
        'coverage_trend' => 'decimal:2',
        'flaky_tests_detected' => 'integer',
        'flaky_tests_fixed' => 'integer',
        'avg_flakiness_score' => 'decimal:2',
        'active_contributors' => 'integer',
        'total_commits' => 'integer',
        'total_code_changes' => 'integer',
        'alerts_triggered' => 'integer',
        'alerts_resolved' => 'integer',
        'is_final' => 'boolean',
        'calculated_at' => 'datetime',
        'metadata' => 'array',
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
    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('metric_date', [$from, $to]);
    }

    public function scopeFinal(Builder $query): Builder
    {
        return $query->where('is_final', true);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('metric_date', '>=', now()->subDays($days)->toDateString());
    }
}