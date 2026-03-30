<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operation_report_run_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_report_run_id')->constrained('operation_report_runs')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedBigInteger('start_operation_id');
            $table->unsignedBigInteger('end_operation_id');
            $table->string('status')->index();
            $table->string('output_file_path')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->json('metrics')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['operation_report_run_id', 'chunk_index'], 'idx_orrc_run_chunk');
            $table->index(['operation_report_run_id', 'status'], 'idx_orrc_run_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_report_run_chunks');
    }
};
