<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook Events Table Migration
 * 
 * Stores incoming webhook events from GitHub/GitLab for audit and replay.
 * Uses JSON column for full payload storage (flexible schema).
 * Implements idempotency via delivery_id to prevent duplicate processing.
 * 
 * RETENTION POLICY: 30 days (Laravel Prunable trait)
 * PARTITIONING: Consider time-based partitioning if volume > 10K/day
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
        Schema::create('webhook_events', function (Blueprint $table) {
            // Primary key
            $table->id()->comment('Auto-incrementing primary key');
            
            // Foreign key (NO FK CONSTRAINT - PlanetScale compatible)
            $table->unsignedBigInteger('repository_id')
                ->nullable()
                ->comment('Repository ID (repositories.id), null for org-level events');
            
            // Webhook metadata
            $table->string('provider', 20)
                ->default('github')
                ->comment('Webhook provider: github, gitlab, bitbucket');
            
            $table->string('event_type', 50)
                ->comment('Event type: pull_request, push, check_run, etc.');
            
            $table->string('action', 50)
                ->nullable()
                ->comment('Event action: opened, closed, synchronize, etc.');
            
            $table->string('delivery_id', 100)
                ->unique()
                ->comment('Unique delivery ID from webhook provider (idempotency key)');
            
            // Signature verification
            $table->string('signature', 255)
                ->comment('Webhook signature (e.g., X-Hub-Signature-256)');
            
            $table->boolean('signature_verified')
                ->default(false)
                ->comment('Whether signature was verified');
            
            $table->timestamp('verified_at')
                ->nullable()
                ->comment('Timestamp when signature was verified');
            
            // Full payload (JSON)
            $table->json('payload')
                ->comment('Complete webhook payload from provider');
            
            // Generated columns from JSON (for indexing and fast queries)
            // NOTE: Generated columns only supported on MySQL/MariaDB, not PlanetScale
            // Comment out if using PlanetScale
            $table->bigInteger('pull_request_number')
                ->nullable()
                ->storedAs("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.number'))")
                ->comment('PR number extracted from payload');
            
            $table->string('pr_action', 50)
                ->nullable()
                ->storedAs("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.action'))")
                ->comment('PR action extracted from payload');
            
            // Processing status
            $table->string('status', 20)
                ->default('pending')
                ->comment('Processing status: pending, processing, completed, failed');
            
            $table->timestamp('processed_at')
                ->nullable()
                ->comment('Timestamp when webhook was processed');
            
            $table->unsignedInteger('processing_duration')
                ->nullable()
                ->comment('Processing duration in milliseconds');
            
            $table->text('error_message')
                ->nullable()
                ->comment('Error message if processing failed');
            
            $table->unsignedTinyInteger('retry_count')
                ->default(0)
                ->comment('Number of processing retry attempts');
            
            $table->timestamp('retry_after')
                ->nullable()
                ->comment('Timestamp for next retry attempt');
            
            // Request metadata
            $table->ipAddress('source_ip')
                ->nullable()
                ->comment('Source IP address of webhook request');
            
            $table->string('user_agent', 255)
                ->nullable()
                ->comment('User agent from webhook request');
            
            $table->json('headers')
                ->nullable()
                ->comment('HTTP headers from webhook request');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index('repository_id', 'idx_webhook_repository_id');
            $table->index('event_type', 'idx_webhook_event_type');
            $table->index('status', 'idx_webhook_status');
            $table->index('created_at', 'idx_webhook_created_at');
            
            // Composite indexes for common queries
            $table->index(['repository_id', 'event_type', 'created_at'], 'idx_webhook_repo_event_created');
            $table->index(['status', 'retry_after'], 'idx_webhook_status_retry');
            $table->index(['event_type', 'action'], 'idx_webhook_event_action');
            
            // Index on generated columns (if using MySQL, not PlanetScale)
            $table->index('pull_request_number', 'idx_webhook_pr_number');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE webhook_events COMMENT = 'Incoming webhook events from GitHub/GitLab with full payload storage'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};