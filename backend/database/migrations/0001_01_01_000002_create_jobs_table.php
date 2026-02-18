<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jobs Table Migration
 * 
 * Queue table for database-backed queue driver (fallback).
 * Primary queue is Redis, this is backup for reliability.
 * 
 * NOTE: Not typically used in production (Redis is faster).
 * Included for local development without Redis.
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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id()->comment('Auto-incrementing primary key');
            
            $table->string('queue', 100)
                ->index()
                ->comment('Queue name');
            
            $table->longText('payload')
                ->comment('Serialized job data');
            
            $table->unsignedTinyInteger('attempts')
                ->comment('Number of processing attempts');
            
            $table->unsignedInteger('reserved_at')
                ->nullable()
                ->comment('Timestamp when job was reserved for processing');
            
            $table->unsignedInteger('available_at')
                ->comment('Timestamp when job becomes available');
            
            $table->unsignedInteger('created_at')
                ->comment('Timestamp when job was created');
            
            // Indexes for queue processing
            $table->index(['queue', 'available_at', 'reserved_at'], 'idx_jobs_queue_processing');
        });
        
        // Add table comment
        DB::statement("ALTER TABLE jobs COMMENT = 'Database-backed queue jobs (fallback, primary is Redis)'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};