<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repositories Table Migration
 * 
 * Stores GitHub/GitLab repository metadata for tracked projects.
 * Each repository can have multiple PRs, webhooks, and test configurations.
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
        Schema::create('repositories', function (Blueprint $table) {
            // Primary key
            $table->id()->comment('Auto-incrementing primary key');
            
            // GitHub/GitLab metadata
            $table->bigInteger('external_id')
                ->unique()
                ->comment('GitHub/GitLab repository ID (e.g., 12345678)');
            
            $table->string('provider', 20)
                ->default('github')
                ->comment('Git provider: github, gitlab, bitbucket');
            
            $table->string('full_name', 255)
                ->comment('Repository full name (e.g., "owner/repo")');
            
            $table->string('name', 100)
                ->comment('Repository name only (e.g., "repo")');
            
            $table->string('owner', 100)
                ->comment('Repository owner/organization');
            
            $table->string('default_branch', 100)
                ->default('main')
                ->comment('Default branch name (main, master, develop)');
            
            $table->text('description')
                ->nullable()
                ->comment('Repository description from GitHub');
            
            $table->string('language', 50)
                ->nullable()
                ->comment('Primary programming language');
            
            // Repository URLs
            $table->string('html_url', 500)
                ->comment('Browser URL (e.g., https://github.com/owner/repo)');
            
            $table->string('clone_url', 500)
                ->nullable()
                ->comment('Git clone URL for repository access');
            
            // Repository statistics (cached from GitHub API)
            $table->unsignedInteger('stars_count')
                ->default(0)
                ->comment('Number of stars (updated periodically)');
            
            $table->unsignedInteger('forks_count')
                ->default(0)
                ->comment('Number of forks');
            
            $table->unsignedInteger('open_issues_count')
                ->default(0)
                ->comment('Number of open issues');
            
            // Webhook configuration
            $table->string('webhook_secret', 100)
                ->nullable()
                ->comment('Webhook signature verification secret');
            
            $table->timestamp('webhook_verified_at')
                ->nullable()
                ->comment('Last successful webhook verification');
            
            // CI/CD integration
            $table->boolean('ci_enabled')
                ->default(true)
                ->comment('Whether CI analysis is enabled');
            
            $table->json('ci_config')
                ->nullable()
                ->comment('CI provider settings (GitHub Actions, CircleCI, etc.)');
            
            // Feature flags
            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether repository is actively tracked');
            
            $table->boolean('is_private')
                ->default(false)
                ->comment('Repository visibility (public/private)');
            
            // Metadata
            $table->json('metadata')
                ->nullable()
                ->comment('Additional repository metadata (topics, custom fields)');
            
            // Timestamps
            $table->timestamp('last_synced_at')
                ->nullable()
                ->comment('Last successful sync with GitHub API');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('provider', 'idx_repositories_provider');
            $table->index('owner', 'idx_repositories_owner');
            $table->index('is_active', 'idx_repositories_is_active');
            $table->index(['provider', 'owner'], 'idx_repositories_provider_owner');
            $table->index('created_at', 'idx_repositories_created_at');
            $table->index('deleted_at', 'idx_repositories_deleted_at');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE repositories COMMENT = 'GitHub/GitLab repositories being tracked for CI insights'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};