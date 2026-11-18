<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->unique();
            $table->string('event_type', 32)->index();
            $table->string('severity', 16)->default('info')->index();
            $table->foreignId('actor_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('actor_role', 32)->nullable();
            $table->string('ip_hash', 64);
            $table->string('endpoint', 255);
            $table->unsignedSmallInteger('http_status')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['event_type', 'severity'], 'sae_event_severity_index');
            $table->index(['event_type', 'created_at'], 'sae_event_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_events');
    }
};