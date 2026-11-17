<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secure_integration_envelopes', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->unique();
            $table->foreignId('internship_application_id')
                ->nullable()
                ->constrained('internship_applications')
                ->nullOnDelete();
            $table->string('direction', 32)->index();
            $table->string('key_id', 16)->index();
            $table->string('cipher_suite', 64)->default('AES-256-GCM+HMAC-SHA256');
            $table->string('aad', 190);
            $table->string('iv_hex', 24);
            $table->longText('ciphertext_b64');
            $table->string('tag_b64', 32);
            $table->string('payload_sha256', 64);
            $table->boolean('signature_verified')->default(false);
            $table->boolean('replay_checked')->default(false);
            $table->string('verification_status', 32)->index();
            $table->string('failure_reason', 190)->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['direction', 'verification_status'], 'sie_direction_status_index');
            $table->index(['internship_application_id', 'direction'], 'sie_app_direction_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secure_integration_envelopes');
    }
};