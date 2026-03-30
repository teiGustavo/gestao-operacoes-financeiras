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
        Schema::table('operation_import_runs', function (Blueprint $table) {
            $table->string('error_code')->nullable()->after('failure_message');
        });

        Schema::table('operation_report_runs', function (Blueprint $table) {
            $table->string('error_code')->nullable()->after('failure_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_import_runs', function (Blueprint $table) {
            $table->dropColumn('error_code');
        });

        Schema::table('operation_report_runs', function (Blueprint $table) {
            $table->dropColumn('error_code');
        });
    }
};
