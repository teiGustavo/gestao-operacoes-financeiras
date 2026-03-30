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
        Schema::table('operation_import_run_chunks', function (Blueprint $table) {
            $table->unsignedBigInteger('start_byte_offset')->nullable()->after('end_line_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_import_run_chunks', function (Blueprint $table) {
            $table->dropColumn('start_byte_offset');
        });
    }
};
