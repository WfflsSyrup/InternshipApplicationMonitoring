<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_application_id')
                ->constrained('internship_applications')
                ->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_role', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['internship_application_id', 'created_at'], 'application_status_logs_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_logs');
    }
};