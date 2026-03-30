<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('operations')) {
            return;
        }

        if ($this->constraintExists('chk_operations_payment_date_status')) {
            DB::statement('ALTER TABLE operations DROP CONSTRAINT chk_operations_payment_date_status');
        }

        DB::statement(<<<'SQL'
            ALTER TABLE operations ADD CONSTRAINT chk_operations_payment_date_status
            CHECK (
                (status = 'disbursed' AND payment_date IS NOT NULL)
               OR (status <> 'disbursed' AND payment_date IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('operations')) {
            return;
        }

        if ($this->constraintExists('chk_operations_payment_date_status')) {
            DB::statement('ALTER TABLE operations DROP CONSTRAINT chk_operations_payment_date_status');
        }

        DB::statement(<<<'SQL'
            ALTER TABLE operations ADD CONSTRAINT chk_operations_payment_date_status
            CHECK (
                (status = 'disbursed' AND payment_date IS NOT NULL)
               OR (status <> 'disbursed' AND payment_date IS NULL)
            )
        SQL);
    }

    private function constraintExists(string $constraintName): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'operations')
            ->where('CONSTRAINT_NAME', $constraintName)
            ->exists();
    }
};
