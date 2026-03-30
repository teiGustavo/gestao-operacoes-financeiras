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
        Schema::create('operation_import_staging_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_import_run_id')->constrained('operation_import_runs')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->json('row_payload');
            $table->string('status')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['operation_import_run_id', 'line_number'], 'idx_oisr_run_line');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_import_staging_rows');
    }
};
