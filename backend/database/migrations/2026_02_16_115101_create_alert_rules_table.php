<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alert Rules Table Migration
 * 
 * Defines alerting rules for various conditions:
 * - Flaky test detection (> 3 failures in 10 runs)
 * - Stale PRs (no activity for 14 days)
 * - Coverage regression (> 5% drop)
 * - CI failure spikes (> 50% failure rate)
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
        Schema::create('alert_rules', function (Blueprint $table) {
            // Primary key
            $table->id()->comment('Auto-incrementing primary key');
            
            // Foreign keys (NO FK CONSTRAINTS - PlanetScale compatible)
            $table->unsignedBigInteger('repository_id')
                ->nullable()
                ->comment('Repository ID (repositories.id), null for global rules');
            
            $table->unsignedBigInteger('created_by_user_id')
                ->comment('User who created this rule (users.id)');
            
            // Rule identification
            $table->string('name', 255)
                ->comment('Human-readable rule name');
            
            $table->text('description')
                ->nullable()
                ->comment('Rule description');
            
            $table->string('rule_type', 50)
                ->comment('Rule type: flaky_test, stale_pr, coverage_drop, ci_failure_spike');
            
            // Rule conditions (JSON for flexibility)
            $table->json('conditions')
                ->comment('Rule conditions as JSON (threshold, timeframe, comparison)');
            
            // Example conditions JSON:
            // {
            //   "metric": "flakiness_score",
            //   "operator": "greater_than",
            //   "threshold": 30,
            //   "timeframe": "7_days",
            //   "consecutive_violations": 3
            // }
            
            // Severity and priority
            $table->string('severity', 20)
                ->default('medium')
                ->comment('Alert severity: low, medium, high, critical');
            
            $table->unsignedTinyInteger('priority')
                ->default(5)
                ->comment('Alert priority 1-10 (10 = highest)');
            
            // Notification channels
            $table->json('notification_channels')
                ->comment('Channels to notify: ["email", "slack", "database"]');
            
            $table->json('notification_recipients')
                ->nullable()
                ->comment('User IDs or email addresses to notify');
            
            // Cooldown and throttling
            $table->unsignedInteger('cooldown_minutes')
                ->default(60)
                ->comment('Minimum minutes between alerts for same condition');
            
            $table->unsignedInteger('max_alerts_per_day')
                ->default(10)
                ->comment('Maximum alerts to send per day (prevent spam)');
            
            // Alert template
            $table->text('message_template')
                ->nullable()
                ->comment('Alert message template (Markdown supported)');
            
            // Schedule (when to evaluate rule)
            $table->json('schedule')
                ->nullable()
                ->comment('Evaluation schedule (cron expression or interval)');
            
            // Rule status
            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether rule is currently active');
            
            $table->timestamp('last_evaluated_at')
                ->nullable()
                ->comment('Last time rule was evaluated');
            
            $table->timestamp('last_triggered_at')
                ->nullable()
                ->comment('Last time rule triggered an alert');
            
            $table->unsignedInteger('trigger_count')
                ->default(0)
                ->comment('Total number of times rule has triggered');
            
            // Metadata
            $table->json('metadata')
                ->nullable()
                ->comment('Additional metadata (tags, custom fields)');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('repository_id', 'idx_alertrule_repository_id');
            $table->index('rule_type', 'idx_alertrule_rule_type');
            $table->index('is_active', 'idx_alertrule_is_active');
            $table->index('severity', 'idx_alertrule_severity');
            $table->index('last_evaluated_at', 'idx_alertrule_last_evaluated_at');
            
            // Composite indexes
            $table->index(['repository_id', 'is_active', 'rule_type'], 'idx_alertrule_repo_active_type');
            $table->index('deleted_at', 'idx_alertrule_deleted_at');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE alert_rules COMMENT = 'Alert rules for automated notifications on CI/PR conditions'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};