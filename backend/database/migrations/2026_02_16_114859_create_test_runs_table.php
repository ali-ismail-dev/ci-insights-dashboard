<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test Runs Table Migration
 * 
 * Stores CI test run results for flakiness detection and coverage tracking.
 * High-volume table - implements retention policy and partitioning strategy.
 * 
 * DESIGN DECISION: Store test failures in JSON (avoid EAV pattern)
 * RETENTION POLICY: 90 days (Laravel Prunable trait)
 * PARTITIONING: Monthly partitions when volume > 100K records
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
        Schema::create('test_runs', function (Blueprint $table) {
            // Primary key
            $table->id()->comment('Auto-incrementing primary key');
            
            // Foreign keys (NO FK CONSTRAINTS - PlanetScale compatible)
            $table->unsignedBigInteger('repository_id')
                ->comment('Repository ID (repositories.id)');
            
            $table->unsignedBigInteger('pull_request_id')
                ->nullable()
                ->comment('PR ID (pull_requests.id), null for branch builds');
            
            // CI run metadata
            $table->string('ci_provider', 50)
                ->comment('CI provider: github_actions, circleci, jenkins, etc.');
            
            $table->string('external_id', 100)
                ->comment('CI run ID from provider (e.g., workflow_run_id)');
            
            $table->string('workflow_name', 255)
                ->comment('Workflow/pipeline name');
            
            $table->string('job_name', 255)
                ->nullable()
                ->comment('Job name within workflow');
            
            $table->string('branch', 255)
                ->comment('Branch where tests ran');
            
            $table->string('commit_sha', 40)
                ->comment('Git commit SHA for this test run');
            
            // Test results
            $table->string('status', 20)
                ->comment('Test run status: success, failure, error, canceled');
            
            $table->unsignedInteger('total_tests')
                ->default(0)
                ->comment('Total number of tests executed');
            
            $table->unsignedInteger('passed_tests')
                ->default(0)
                ->comment('Number of tests that passed');
            
            $table->unsignedInteger('failed_tests')
                ->default(0)
                ->comment('Number of tests that failed');
            
            $table->unsignedInteger('skipped_tests')
                ->default(0)
                ->comment('Number of tests that were skipped');
            
            $table->unsignedInteger('flaky_tests')
                ->default(0)
                ->comment('Number of flaky tests detected (passed on retry)');
            
            // Test coverage
            $table->decimal('line_coverage', 5, 2)
                ->nullable()
                ->comment('Line coverage percentage (0-100)');
            
            $table->decimal('branch_coverage', 5, 2)
                ->nullable()
                ->comment('Branch coverage percentage (0-100)');
            
            $table->decimal('method_coverage', 5, 2)
                ->nullable()
                ->comment('Method/function coverage percentage (0-100)');
            
            // Execution metrics
            $table->unsignedInteger('duration')
                ->nullable()
                ->comment('Total test run duration in seconds');
            
            $table->timestamp('started_at')
                ->nullable()
                ->comment('Test run start timestamp');
            
            $table->timestamp('completed_at')
                ->nullable()
                ->comment('Test run completion timestamp');
            
            // Failed tests details (JSON array)
            $table->json('failed_tests_details')
                ->nullable()
                ->comment('Array of failed test objects with name, error, stacktrace');
            
            // Flaky tests details (JSON array)
            $table->json('flaky_tests_details')
                ->nullable()
                ->comment('Array of flaky test objects with retry history');
            
            // Coverage report
            $table->json('coverage_report')
                ->nullable()
                ->comment('Detailed coverage data (file-level coverage)');
            
            // CI URLs
            $table->string('run_url', 500)
                ->nullable()
                ->comment('URL to view test run on CI provider');
            
            $table->string('logs_url', 500)
                ->nullable()
                ->comment('URL to download test logs');
            
            // Environment info
            $table->json('environment')
                ->nullable()
                ->comment('Test environment details (OS, language version, etc.)');
            
            // Flags
            $table->boolean('is_retry')
                ->default(false)
                ->comment('Whether this is a retry of a previous failed run');
            
            $table->unsignedTinyInteger('retry_attempt')
                ->default(0)
                ->comment('Retry attempt number (0 = first attempt)');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index('repository_id', 'idx_testrun_repository_id');
            $table->index('pull_request_id', 'idx_testrun_pull_request_id');
            $table->index('external_id', 'idx_testrun_external_id');
            $table->index('status', 'idx_testrun_status');
            $table->index('branch', 'idx_testrun_branch');
            $table->index('commit_sha', 'idx_testrun_commit_sha');
            $table->index('started_at', 'idx_testrun_started_at');
            $table->index('created_at', 'idx_testrun_created_at');
            
            // Composite indexes for analytics
            $table->index(['repository_id', 'branch', 'started_at'], 'idx_testrun_repo_branch_started');
            $table->index(['repository_id', 'status', 'started_at'], 'idx_testrun_repo_status_started');
            $table->index(['pull_request_id', 'status'], 'idx_testrun_pr_status');
            $table->index(['flaky_tests', 'started_at'], 'idx_testrun_flaky_started');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE test_runs COMMENT = 'CI test run results for flakiness detection and coverage tracking'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_runs');
    }
};