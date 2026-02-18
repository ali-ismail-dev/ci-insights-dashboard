<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pull Requests Table Migration
 * 
 * Core table for tracking PRs with comprehensive metrics.
 * Designed for efficient time-series queries and analytics.
 * 
 * DECISION: No foreign key constraints to support PlanetScale migration.
 * Referential integrity enforced at application level.
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
        Schema::create('pull_requests', function (Blueprint $table) {
            // Primary key
            $table->id()->comment('Auto-incrementing primary key');
            
            // Foreign keys (NO FK CONSTRAINTS - PlanetScale compatible)
            $table->unsignedBigInteger('repository_id')
                ->comment('Repository ID (repositories.id)');
            
            $table->unsignedBigInteger('author_id')
                ->nullable()
                ->comment('PR author user ID (users.id)');
            
            // GitHub/GitLab metadata
            $table->bigInteger('external_id')
                ->comment('GitHub/GitLab PR ID');
            
            $table->integer('number')
                ->comment('PR number in repository (e.g., #123)');
            
            $table->string('state', 20)
                ->comment('PR state: open, closed, merged');
            
            $table->string('title', 500)
                ->comment('PR title');
            
            $table->text('description')
                ->nullable()
                ->comment('PR description/body (Markdown)');
            
            // Branch information
            $table->string('head_branch', 255)
                ->comment('Source branch name');
            
            $table->string('base_branch', 255)
                ->comment('Target branch name (usually main/master)');
            
            $table->string('head_sha', 40)
                ->comment('Latest commit SHA in head branch');
            
            $table->string('base_sha', 40)
                ->comment('Latest commit SHA in base branch');
            
            // PR URLs
            $table->string('html_url', 500)
                ->comment('Browser URL for PR');
            
            $table->string('diff_url', 500)
                ->nullable()
                ->comment('URL to view diff');
            
            // PR statistics
            $table->unsignedInteger('additions')
                ->default(0)
                ->comment('Lines of code added');
            
            $table->unsignedInteger('deletions')
                ->default(0)
                ->comment('Lines of code deleted');
            
            $table->unsignedInteger('changed_files')
                ->default(0)
                ->comment('Number of files changed');
            
            $table->unsignedInteger('commits_count')
                ->default(0)
                ->comment('Number of commits in PR');
            
            $table->unsignedInteger('comments_count')
                ->default(0)
                ->comment('Number of comments (reviews + discussions)');
            
            // Review status
            $table->string('review_status', 20)
                ->nullable()
                ->comment('Review status: approved, changes_requested, pending');
            
            $table->unsignedInteger('approvals_count')
                ->default(0)
                ->comment('Number of approving reviews');
            
            $table->unsignedInteger('review_comments_count')
                ->default(0)
                ->comment('Number of review comments');
            
            // CI/CD status
            $table->string('ci_status', 20)
                ->nullable()
                ->comment('CI status: success, failure, pending, error');
            
            $table->unsignedInteger('ci_checks_count')
                ->default(0)
                ->comment('Total number of CI checks');
            
            $table->unsignedInteger('ci_checks_passed')
                ->default(0)
                ->comment('Number of passing CI checks');
            
            $table->unsignedInteger('ci_checks_failed')
                ->default(0)
                ->comment('Number of failing CI checks');
            
            // Test metrics
            $table->decimal('test_coverage', 5, 2)
                ->nullable()
                ->comment('Test coverage percentage (0-100)');
            
            $table->unsignedInteger('tests_total')
                ->default(0)
                ->comment('Total tests run');
            
            $table->unsignedInteger('tests_passed')
                ->default(0)
                ->comment('Tests that passed');
            
            $table->unsignedInteger('tests_failed')
                ->default(0)
                ->comment('Tests that failed');
            
            $table->unsignedInteger('tests_skipped')
                ->default(0)
                ->comment('Tests that were skipped');
            
            // Time metrics (in seconds)
            $table->unsignedInteger('cycle_time')
                ->nullable()
                ->comment('Time from first commit to merge (seconds)');
            
            $table->unsignedInteger('time_to_first_review')
                ->nullable()
                ->comment('Time from creation to first review (seconds)');
            
            $table->unsignedInteger('time_to_approval')
                ->nullable()
                ->comment('Time from creation to approval (seconds)');
            
            $table->unsignedInteger('time_to_merge')
                ->nullable()
                ->comment('Time from approval to merge (seconds)');
            
            // Labels and flags
            $table->json('labels')
                ->nullable()
                ->comment('PR labels (bug, feature, documentation, etc.)');
            
            $table->boolean('is_draft')
                ->default(false)
                ->comment('Whether PR is in draft mode');
            
            $table->boolean('is_mergeable')
                ->nullable()
                ->comment('Whether PR can be merged (no conflicts)');
            
            $table->boolean('is_hot')
                ->default(false)
                ->comment('Hot PR flag (high activity, many comments)');
            
            $table->boolean('is_stale')
                ->default(false)
                ->comment('Stale PR flag (no activity for 14+ days)');
            
            // Assignees and reviewers (JSON array of user IDs)
            $table->json('assignees')
                ->nullable()
                ->comment('Assigned user IDs [1, 2, 3]');
            
            $table->json('requested_reviewers')
                ->nullable()
                ->comment('Requested reviewer user IDs');
            
            // Metadata
            $table->json('metadata')
                ->nullable()
                ->comment('Additional metadata (milestone, project, custom fields)');
            
            // Important timestamps
            $table->timestamp('first_commit_at')
                ->nullable()
                ->comment('Timestamp of first commit in PR');
            
            $table->timestamp('first_review_at')
                ->nullable()
                ->comment('Timestamp of first review');
            
            $table->timestamp('approved_at')
                ->nullable()
                ->comment('Timestamp when PR was approved');
            
            $table->timestamp('merged_at')
                ->nullable()
                ->comment('Timestamp when PR was merged');
            
            $table->timestamp('closed_at')
                ->nullable()
                ->comment('Timestamp when PR was closed (merged or abandoned)');
            
            $table->timestamp('last_activity_at')
                ->nullable()
                ->comment('Last activity timestamp (commit, comment, review)');
            
            // Standard timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Composite unique constraint (repository + PR number)
            $table->unique(['repository_id', 'number'], 'uq_repo_pr_number');
            
            // Indexes for common query patterns
            $table->index('external_id', 'idx_pr_external_id');
            $table->index('repository_id', 'idx_pr_repository_id');
            $table->index('author_id', 'idx_pr_author_id');
            $table->index('state', 'idx_pr_state');
            $table->index('ci_status', 'idx_pr_ci_status');
            $table->index('is_stale', 'idx_pr_is_stale');
            $table->index('created_at', 'idx_pr_created_at');
            $table->index('merged_at', 'idx_pr_merged_at');
            
            // Composite indexes for analytics queries
            $table->index(['repository_id', 'state', 'created_at'], 'idx_pr_repo_state_created');
            $table->index(['repository_id', 'merged_at'], 'idx_pr_repo_merged');
            $table->index(['state', 'is_stale', 'updated_at'], 'idx_pr_state_stale_updated');
            $table->index('deleted_at', 'idx_pr_deleted_at');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE pull_requests COMMENT = 'Pull requests with comprehensive metrics for CI insights'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};