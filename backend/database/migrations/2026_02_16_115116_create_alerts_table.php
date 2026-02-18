<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alerts Table Migration
 * 
 * Stores triggered alerts with full context for tracking and resolution.
 * Provides alert history and analytics on notification effectiveness.
 * 
 * RETENTION POLICY: 180 days for audit trail
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
        Schema::create('alerts', function (Blueprint $table) {
            // Primary key
            $table->id()->comment('Auto-incrementing primary key');
            
            // Foreign keys (NO FK CONSTRAINTS - PlanetScale compatible)
            $table->unsignedBigInteger('alert_rule_id')
                ->comment('Alert rule ID (alert_rules.id)');
            
            $table->unsignedBigInteger('repository_id')
                ->nullable()
                ->comment('Repository ID (repositories.id)');
            
            $table->unsignedBigInteger('pull_request_id')
                ->nullable()
                ->comment('PR ID if alert is PR-specific (pull_requests.id)');
            
            // Alert details
            $table->string('alert_type', 50)
                ->comment('Alert type: flaky_test, stale_pr, coverage_drop, etc.');
            
            $table->string('severity', 20)
                ->comment('Alert severity: low, medium, high, critical');
            
            $table->string('title', 500)
                ->comment('Alert title/subject');
            
            $table->text('message')
                ->comment('Alert message body (Markdown)');
            
            // Context data (what triggered the alert)
            $table->json('context')
                ->nullable()
                ->comment('Alert context (test name, failure rate, threshold, etc.)');
            
            // Example context JSON:
            // {
            //   "test_name": "UserAuthenticationTest::testLogin",
            //   "flakiness_score": 45.5,
            //   "threshold": 30,
            //   "failure_count": 5,
            //   "run_count": 11
            // }
            
            // Alert status
            $table->string('status', 20)
                ->default('open')
                ->comment('Alert status: open, acknowledged, resolved, dismissed');
            
            $table->timestamp('acknowledged_at')
                ->nullable()
                ->comment('Timestamp when alert was acknowledged');
            
            $table->unsignedBigInteger('acknowledged_by_user_id')
                ->nullable()
                ->comment('User who acknowledged alert (users.id)');
            
            $table->timestamp('resolved_at')
                ->nullable()
                ->comment('Timestamp when alert was resolved');
            
            $table->unsignedBigInteger('resolved_by_user_id')
                ->nullable()
                ->comment('User who resolved alert (users.id)');
            
            $table->text('resolution_notes')
                ->nullable()
                ->comment('Notes about how alert was resolved');
            
            // Notification tracking
            $table->json('notification_channels')
                ->nullable()
                ->comment('Channels where alert was sent');
            
            $table->json('notification_status')
                ->nullable()
                ->comment('Delivery status per channel (sent, failed, pending)');
            
            $table->timestamp('notified_at')
                ->nullable()
                ->comment('Timestamp when notifications were sent');
            
            // Alert grouping (for digest notifications)
            $table->string('fingerprint', 64)
                ->nullable()
                ->comment('Alert fingerprint for deduplication (hash of context)');
            
            $table->unsignedBigInteger('parent_alert_id')
                ->nullable()
                ->comment('Parent alert ID if this is a duplicate (alerts.id)');
            
            $table->unsignedInteger('occurrence_count')
                ->default(1)
                ->comment('Number of times this alert occurred (deduplicated)');
            
            // Metadata
            $table->json('metadata')
                ->nullable()
                ->comment('Additional metadata');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index('alert_rule_id', 'idx_alert_alert_rule_id');
            $table->index('repository_id', 'idx_alert_repository_id');
            $table->index('pull_request_id', 'idx_alert_pull_request_id');
            $table->index('alert_type', 'idx_alert_alert_type');
            $table->index('status', 'idx_alert_status');
            $table->index('severity', 'idx_alert_severity');
            $table->index('fingerprint', 'idx_alert_fingerprint');
            $table->index('created_at', 'idx_alert_created_at');
            
            // Composite indexes for dashboard queries
            $table->index(['repository_id', 'status', 'created_at'], 'idx_alert_repo_status_created');
            $table->index(['status', 'severity', 'created_at'], 'idx_alert_status_severity_created');
            $table->index(['alert_type', 'status'], 'idx_alert_type_status');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE alerts COMMENT = 'Triggered alerts with full context and resolution tracking'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};