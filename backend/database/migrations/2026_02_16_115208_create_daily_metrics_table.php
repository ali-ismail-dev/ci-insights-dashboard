<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Metrics Table Migration
 * 
 * Pre-aggregated daily metrics for fast dashboard loading.
 * Simulates materialized views (MySQL doesn't have native MVs).
 * Updated daily by scheduled job.
 * 
 * DESIGN DECISION: Denormalized for query performance
 * This is the "read model" in CQRS pattern - write model is in transactional tables.
 * 
 * @package Database\Migrations
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_metrics', function (Blueprint $table) {
            // Primary key
            $table->id()->comment('Auto-incrementing primary key');
            
            // Foreign key (NO FK CONSTRAINT - PlanetScale compatible)
            $table->unsignedBigInteger('repository_id')
                ->comment('Repository ID (repositories.id)');
            
            // Date dimension
            $table->date('metric_date')
                ->comment('Date for these metrics (YYYY-MM-DD)');
            
            // PR metrics
            $table->unsignedInteger('prs_opened')
                ->default(0)
                ->comment('Number of PRs opened on this date');
            
            $table->unsignedInteger('prs_merged')
                ->default(0)
                ->comment('Number of PRs merged on this date');
            
            $table->unsignedInteger('prs_closed')
                ->default(0)
                ->comment('Number of PRs closed without merge');
            
            $table->unsignedInteger('prs_active')
                ->default(0)
                ->comment('Number of active PRs at end of day');
            
            $table->decimal('avg_cycle_time', 10, 2)
                ->nullable()
                ->comment('Average PR cycle time in hours');
            
            $table->decimal('median_cycle_time', 10, 2)
                ->nullable()
                ->comment('Median PR cycle time in hours');
            
            $table->decimal('avg_time_to_first_review', 10, 2)
                ->nullable()
                ->comment('Average time to first review in hours');
            
            $table->decimal('avg_time_to_merge', 10, 2)
                ->nullable()
                ->comment('Average time from approval to merge in hours');
            
            // CI metrics
            $table->unsignedInteger('test_runs_total')
                ->default(0)
                ->comment('Total test runs executed');
            
            $table->unsignedInteger('test_runs_passed')
                ->default(0)
                ->comment('Test runs that passed');
            
            $table->unsignedInteger('test_runs_failed')
                ->default(0)
                ->comment('Test runs that failed');
            
            $table->decimal('ci_success_rate', 5, 2)
                ->nullable()
                ->comment('CI success rate percentage (0-100)');
            
            $table->decimal('avg_test_duration', 10, 2)
                ->nullable()
                ->comment('Average test run duration in minutes');
            
            // Test coverage metrics
            $table->decimal('avg_line_coverage', 5, 2)
                ->nullable()
                ->comment('Average line coverage percentage');
            
            $table->decimal('avg_branch_coverage', 5, 2)
                ->nullable()
                ->comment('Average branch coverage percentage');
            
            $table->decimal('coverage_trend', 6, 2)
                ->nullable()
                ->comment('Coverage trend vs previous day (percentage points)');
            
            // Flakiness metrics
            $table->unsignedInteger('flaky_tests_detected')
                ->default(0)
                ->comment('Number of new flaky tests detected');
            
            $table->unsignedInteger('flaky_tests_fixed')
                ->default(0)
                ->comment('Number of flaky tests fixed');
            
            $table->decimal('avg_flakiness_score', 5, 2)
                ->nullable()
                ->comment('Average flakiness score across all tests');
            
            // Contributor metrics
            $table->unsignedInteger('active_contributors')
                ->default(0)
                ->comment('Number of unique contributors active this day');
            
            $table->unsignedInteger('total_commits')
                ->default(0)
                ->comment('Total commits made');
            
            $table->unsignedInteger('total_code_changes')
                ->default(0)
                ->comment('Total lines changed (additions + deletions)');
            
            // Alert metrics
            $table->unsignedInteger('alerts_triggered')
                ->default(0)
                ->comment('Number of alerts triggered');
            
            $table->unsignedInteger('alerts_resolved')
                ->default(0)
                ->comment('Number of alerts resolved');
            
            // Metadata
            $table->json('metadata')
                ->nullable()
                ->comment('Additional metrics (custom fields)');
            
            // Calculation metadata
            $table->timestamp('calculated_at')
                ->nullable()
                ->comment('When these metrics were calculated');
            
            $table->boolean('is_final')
                ->default(false)
                ->comment('Whether metrics are final (day ended) or provisional');
            
            // Timestamps
            $table->timestamps();
            
            // Unique constraint (one record per repo per day)
            $table->unique(['repository_id', 'metric_date'], 'uq_repo_date');
            
            // Indexes for time-series queries
            $table->index('repository_id', 'idx_dailymetrics_repository_id');
            $table->index('metric_date', 'idx_dailymetrics_metric_date');
            $table->index(['repository_id', 'metric_date'], 'idx_dailymetrics_repo_date');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE daily_metrics COMMENT = 'Pre-aggregated daily metrics for fast dashboard queries (materialized view simulation)'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_metrics');
    }
};