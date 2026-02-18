<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File Changes Table Migration
 * 
 * Tracks files modified in each PR for CI failure correlation.
 * Critical for answering: "Which files cause CI failures most often?"
 * 
 * DESIGN DECISION: Store per-PR file changes (not per-commit)
 * This avoids massive volume while maintaining correlation accuracy.
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
        Schema::create('file_changes', function (Blueprint $table) {
            // Primary key
            $table->id()->comment('Auto-incrementing primary key');
            
            // Foreign keys (NO FK CONSTRAINTS - PlanetScale compatible)
            $table->unsignedBigInteger('repository_id')
                ->comment('Repository ID (repositories.id)');
            
            $table->unsignedBigInteger('pull_request_id')
                ->comment('PR ID (pull_requests.id)');
            
            // File information
            $table->string('file_path', 500)
                ->comment('File path relative to repository root');
            
            $table->string('file_extension', 20)
                ->nullable()
                ->comment('File extension (e.g., .php, .js, .py)');
            
            $table->string('directory', 500)
                ->nullable()
                ->comment('Directory path (for grouping by module)');
            
            $table->string('change_type', 20)
                ->comment('Change type: added, modified, deleted, renamed');
            
            // Change statistics
            $table->unsignedInteger('additions')
                ->default(0)
                ->comment('Lines added in this file');
            
            $table->unsignedInteger('deletions')
                ->default(0)
                ->comment('Lines deleted in this file');
            
            $table->unsignedInteger('changes')
                ->default(0)
                ->comment('Total changes (additions + deletions)');
            
            // Rename tracking
            $table->string('previous_file_path', 500)
                ->nullable()
                ->comment('Previous file path if renamed');
            
            // CI correlation data (denormalized for fast queries)
            $table->boolean('caused_ci_failure')
                ->default(false)
                ->comment('Whether changes to this file correlated with CI failure');
            
            $table->unsignedInteger('ci_failure_count')
                ->default(0)
                ->comment('Number of CI failures in runs that touched this file');
            
            // Historical metrics (updated periodically)
            $table->decimal('failure_rate', 5, 2)
                ->nullable()
                ->comment('Historical failure rate for this file path (0-100)');
            
            $table->unsignedInteger('total_changes_count')
                ->default(0)
                ->comment('Total number of times this file was changed');
            
            // Metadata
            $table->json('metadata')
                ->nullable()
                ->comment('Additional metadata (blame info, complexity metrics)');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index('repository_id', 'idx_filechange_repository_id');
            $table->index('pull_request_id', 'idx_filechange_pull_request_id');
            $table->index('file_path', 'idx_filechange_file_path');
            $table->index('file_extension', 'idx_filechange_file_extension');
            $table->index('change_type', 'idx_filechange_change_type');
            $table->index('caused_ci_failure', 'idx_filechange_caused_ci_failure');
            
            // Composite indexes for correlation queries
            $table->index(['repository_id', 'file_path', 'created_at'], 'idx_filechange_repo_path_created');
            $table->index(['file_path', 'caused_ci_failure'], 'idx_filechange_path_failure');
            $table->index(['repository_id', 'caused_ci_failure', 'created_at'], 'idx_filechange_repo_failure_created');
            
            // Unique constraint to prevent duplicate entries
            $table->unique(['pull_request_id', 'file_path'], 'uq_pr_file_path');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE file_changes COMMENT = 'File changes per PR for CI failure correlation analysis'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_changes');
    }
};