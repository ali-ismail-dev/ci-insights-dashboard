<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test Results Table Migration
 * 
 * Stores individual test results for flakiness analysis across runs.
 * Tracks test history to identify patterns and calculate flakiness score.
 * 
 * DESIGN DECISION: Separate table from test_runs for better normalization
 * RETENTION POLICY: 90 days (Laravel Prunable trait)
 * VOLUME: Expect 10K-100K records/day (200 tests × 50 runs/day)
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
        Schema::create('test_results', function (Blueprint $table) {
            // Primary key
            $table->id()->comment('Auto-incrementing primary key');
            
            // Foreign keys (NO FK CONSTRAINTS - PlanetScale compatible)
            $table->unsignedBigInteger('test_run_id')
                ->comment('Test run ID (test_runs.id)');
            
            $table->unsignedBigInteger('repository_id')
                ->comment('Repository ID (repositories.id)');
            
            // Test identification
            $table->string('test_identifier', 500)
                ->comment('Unique test identifier (file::class::method or full path)');
            
            $table->string('test_name', 500)
                ->comment('Human-readable test name');
            
            $table->string('test_file', 500)
                ->comment('Test file path relative to repository root');
            
            $table->string('test_class', 255)
                ->nullable()
                ->comment('Test class name');
            
            $table->string('test_method', 255)
                ->nullable()
                ->comment('Test method name');
            
            // Test result
            $table->string('status', 20)
                ->comment('Test status: passed, failed, skipped, error');
            
            $table->unsignedInteger('duration')
                ->nullable()
                ->comment('Test execution duration in milliseconds');
            
            // Failure details
            $table->text('error_message')
                ->nullable()
                ->comment('Error message if test failed');
            
            $table->text('stack_trace')
                ->nullable()
                ->comment('Stack trace of failure');
            
            $table->string('failure_type', 100)
                ->nullable()
                ->comment('Failure type: assertion, exception, timeout, etc.');
            
            // Flakiness detection
            $table->boolean('is_flaky')
                ->default(false)
                ->comment('Whether test was marked as flaky in this run');
            
            $table->boolean('passed_on_retry')
                ->default(false)
                ->comment('Whether test passed after retry');
            
            $table->unsignedTinyInteger('retry_count')
                ->default(0)
                ->comment('Number of retries before pass/fail');
            
            // Historical flakiness score (calculated periodically)
            $table->decimal('flakiness_score', 5, 2)
                ->nullable()
                ->comment('Flakiness score 0-100 (100 = always flaky)');
            
            $table->unsignedInteger('failure_rate')
                ->nullable()
                ->comment('Failure rate in last 30 runs (percentage)');
            
            // Assertions (for debugging)
            $table->unsignedInteger('assertions_count')
                ->nullable()
                ->comment('Number of assertions in test');
            
            // Test metadata
            $table->json('tags')
                ->nullable()
                ->comment('Test tags/categories (unit, integration, smoke, etc.)');
            
            $table->json('metadata')
                ->nullable()
                ->comment('Additional test metadata');
            
            // Timestamps
            $table->timestamp('executed_at')
                ->useCurrent()
                ->comment('Timestamp when test was executed');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index('test_run_id', 'idx_testresult_test_run_id');
            $table->index('repository_id', 'idx_testresult_repository_id');
            $table->index('test_identifier', 'idx_testresult_identifier');
            $table->index('status', 'idx_testresult_status');
            $table->index('is_flaky', 'idx_testresult_is_flaky');
            $table->index('executed_at', 'idx_testresult_executed_at');
            
            // Composite indexes for flakiness queries
            $table->index(['repository_id', 'test_identifier', 'executed_at'], 'idx_testresult_repo_id_executed');
            $table->index(['test_identifier', 'status', 'executed_at'], 'idx_testresult_id_status_executed');
            $table->index(['is_flaky', 'executed_at'], 'idx_testresult_flaky_executed');
            
            // Full-text index for test name search (MySQL only, not PlanetScale)
            $table->fullText(['test_name', 'test_file'], 'ft_testresult_name_file');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE test_results COMMENT = 'Individual test results for flakiness detection and history tracking'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};