<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Failed Jobs Table Migration
 * 
 * Laravel's default failed jobs table for dead-letter queue.
 * Stores jobs that exceeded retry limits for manual intervention.
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
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id()->comment('Auto-incrementing primary key');
            
            $table->string('uuid', 100)
                ->unique()
                ->comment('Unique job UUID for identification');
            
            $table->text('connection')
                ->comment('Queue connection name (redis, database, etc.)');
            
            $table->text('queue')
                ->comment('Queue name (default, high, low, etc.)');
            
            $table->longText('payload')
                ->comment('Serialized job payload (class, data, retries)');
            
            $table->longText('exception')
                ->comment('Exception message and stack trace');
            
            $table->timestamp('failed_at')
                ->useCurrent()
                ->comment('Timestamp when job failed');
            
            // Indexes
            $table->index('failed_at', 'idx_failedjobs_failed_at');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE failed_jobs COMMENT = 'Failed background jobs for manual retry and debugging'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};