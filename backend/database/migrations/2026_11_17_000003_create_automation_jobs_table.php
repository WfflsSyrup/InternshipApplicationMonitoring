<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('internship_application_id')
                ->nullable()
                ->constrained('internship_applications')
                ->nullOnDelete();
            $table->string('automation_type', 32)->index();
            $table->string('recipient_role', 32);
            $table->string('recipient_identifier', 190)->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->string('failure_class', 16)->nullable()->index();
            $table->unsignedSmallInteger('last_error_code')->nullable();
            $table->string('last_error_summary', 500)->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('coordinator_remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['internship_application_id', 'automation_type']);
            $table->index(['automation_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_jobs');
    }
};