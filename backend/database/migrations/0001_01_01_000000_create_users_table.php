<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users Table Migration
 * 
 * Stores user accounts with OAuth integration (GitHub/GitLab).
 * Supports both dashboard users and tracked contributors.
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
        Schema::create('users', function (Blueprint $table) {
            // Primary key
            $table->id()->comment('Auto-incrementing primary key');
            
            // GitHub/GitLab OAuth
            $table->bigInteger('external_id')
                ->unique()
                ->nullable()
                ->comment('GitHub/GitLab user ID from OAuth');
            
            $table->string('provider', 20)
                ->default('github')
                ->comment('OAuth provider: github, gitlab');
            
            $table->string('username', 100)
                ->unique()
                ->comment('GitHub/GitLab username (login handle)');
            
            // User profile
            $table->string('name', 255)
                ->nullable()
                ->comment('Full name from GitHub profile');
            
            $table->string('email', 255)
                ->unique()
                ->comment('Primary email address');
            
            $table->timestamp('email_verified_at')
                ->nullable()
                ->comment('Email verification timestamp');
            
            $table->string('avatar_url', 500)
                ->nullable()
                ->comment('Profile picture URL from GitHub');
            
            $table->text('bio')
                ->nullable()
                ->comment('User bio from GitHub profile');
            
            $table->string('location', 100)
                ->nullable()
                ->comment('User location');
            
            $table->string('company', 100)
                ->nullable()
                ->comment('User company/organization');
            
            $table->string('website_url', 500)
                ->nullable()
                ->comment('User website or blog URL');
            
            // Authentication
            $table->string('password')
                ->nullable()
                ->comment('Hashed password (null for OAuth-only users)');
            
            $table->rememberToken()
                ->comment('Remember me token for persistent login');
            
            // OAuth tokens (encrypted at application level)
            $table->text('oauth_token')
                ->nullable()
                ->comment('Encrypted OAuth access token');
            
            $table->text('oauth_refresh_token')
                ->nullable()
                ->comment('Encrypted OAuth refresh token');
            
            $table->timestamp('oauth_expires_at')
                ->nullable()
                ->comment('OAuth token expiration timestamp');
            
            // Permissions & roles
            $table->string('role', 20)
                ->default('viewer')
                ->comment('User role: admin, member, viewer');
            
            $table->json('permissions')
                ->nullable()
                ->comment('Granular permissions (can_view_private_repos, etc.)');
            
            // User preferences
            $table->json('preferences')
                ->nullable()
                ->comment('Dashboard preferences (theme, notifications, etc.)');
            
            $table->string('timezone', 50)
                ->default('UTC')
                ->comment('User timezone for date formatting');
            
            // Notifications
            $table->boolean('email_notifications')
                ->default(true)
                ->comment('Enable email notifications');
            
            $table->boolean('slack_notifications')
                ->default(false)
                ->comment('Enable Slack notifications');
            
            $table->string('slack_webhook_url', 500)
                ->nullable()
                ->comment('Slack incoming webhook URL (encrypted)');
            
            // Activity tracking
            $table->timestamp('last_login_at')
                ->nullable()
                ->comment('Last successful login timestamp');
            
            $table->ipAddress('last_login_ip')
                ->nullable()
                ->comment('IP address of last login');
            
            $table->timestamp('last_activity_at')
                ->nullable()
                ->comment('Last activity timestamp (any action)');
            
            // Account status
            $table->boolean('is_active')
                ->default(true)
                ->comment('Account active status');
            
            $table->timestamp('suspended_at')
                ->nullable()
                ->comment('Account suspension timestamp');
            
            $table->text('suspension_reason')
                ->nullable()
                ->comment('Reason for account suspension');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('provider', 'idx_users_provider');
            $table->index('role', 'idx_users_role');
            $table->index('is_active', 'idx_users_is_active');
            $table->index('last_login_at', 'idx_users_last_login_at');
            $table->index('created_at', 'idx_users_created_at');
            $table->index('deleted_at', 'idx_users_deleted_at');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE users COMMENT = 'Application users with GitHub/GitLab OAuth integration'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};