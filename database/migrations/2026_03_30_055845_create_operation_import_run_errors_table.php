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
        Schema::create('operation_import_run_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_import_run_id')->constrained('operation_import_runs')->cascadeOnDelete();
            $table->unsignedInteger('line_number')->nullable();
            $table->text('message');
            $table->json('row_payload')->nullable();
            $table->timestamps();

            $table->index(['operation_import_run_id', 'line_number'], 'idx_oire_run_line');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_import_run_errors');
    }
};
