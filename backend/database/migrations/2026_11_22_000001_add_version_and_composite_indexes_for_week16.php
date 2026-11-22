<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('internship_applications', 'version')) {
            Schema::table('internship_applications', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('placement_acknowledged_at');
            });
        }

        Schema::table('internship_applications', function (Blueprint $table) {
            $table->index(['status', 'updated_at'], 'ia_status_updated_at_index');
        });

        Schema::table('application_status_logs', function (Blueprint $table) {
            $table->index(['internship_application_id', 'created_at'], 'asl_app_created_at_index');
        });

        Schema::table('automation_jobs', function (Blueprint $table) {
            $table->index(['status', 'next_retry_at'], 'aj_status_next_retry_index');
        });

        Schema::create('performance_benchmark_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->unique();
            $table->string('scenario_name', 64)->index();
            $table->unsignedInteger('virtual_users')->default(1);
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('conflict_or_throttled_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->decimal('p50_ms', 8, 2)->default(0);
            $table->decimal('p95_ms', 8, 2)->default(0);
            $table->decimal('p99_ms', 8, 2)->default(0);
            $table->decimal('avg_queries_per_req', 6, 2)->default(0);
            $table->decimal('throughput_rps', 8, 2)->default(0);
            $table->boolean('slo_passed')->default(false)->index();
            $table->foreignId('executed_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_benchmark_runs');

        Schema::table('automation_jobs', function (Blueprint $table) {
            $table->dropIndex('aj_status_next_retry_index');
        });

        Schema::table('application_status_logs', function (Blueprint $table) {
            $table->dropIndex('asl_app_created_at_index');
        });

        Schema::table('internship_applications', function (Blueprint $table) {
            $table->dropIndex('ia_status_updated_at_index');
            $table->dropColumn('version');
        });
    }
};