<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('partner_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('host_company_name');
            $table->string('target_role');
            $table->unsignedInteger('internship_hours_required')->default(0);
            $table->date('target_start_date');
            $table->json('requirements_checklist')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->dateTime('interview_schedule')->nullable();
            $table->text('interview_notes')->nullable();
            $table->timestamp('placement_confirmed_at')->nullable();
            $table->timestamp('placement_acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['adviser_id', 'status']);
            $table->index(['partner_reviewer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_applications');
    }
};