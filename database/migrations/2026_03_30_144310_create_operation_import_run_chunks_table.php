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
        Schema::create('operation_import_run_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_import_run_id')->constrained('operation_import_runs')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('start_line_number');
            $table->unsignedInteger('end_line_number');
            $table->string('status')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->json('error_summary')->nullable();
            $table->json('metrics')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['operation_import_run_id', 'chunk_index'], 'idx_oirc_run_chunk');
            $table->index(['operation_import_run_id', 'status'], 'idx_oirc_run_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_import_run_chunks');
    }
};
