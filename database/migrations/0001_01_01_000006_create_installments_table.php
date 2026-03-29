<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('value', 15, 2);
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by_user_id')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('operation_id')
                ->references('id')->on('operations')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('paid_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete()
                ->onUpdate('cascade');

            // Índices
            $table->index('operation_id');
            $table->index(['operation_id', 'paid'], 'idx_installments_operation_paid');
            $table->index('due_date');
            $table->index(['operation_id', 'due_date', 'paid'], 'idx_installments_operation_due');
            $table->unique(['operation_id', 'installment_number'], 'unique_installment_number');
        });

        if (DB::getDriverName() !== 'mysql') {
            if (isset($this->command)) {
                $this->command->warn('Aviso: Você não está usando MySQL. Algumas constraints de CHECK podem falhar.');
            }

            return;
        }

        if (!Schema::hasTable('installments')) {
            if (isset($this->command)) {
                $this->command->warn("Aviso: A tabela 'installments' não foi criada. Os CHECKs não serão criados.");
            }

            return;
        }

        DB::statement(<<<SQL
            ALTER TABLE installments
            ADD CONSTRAINT chk_installments_number
            CHECK (installment_number > 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE installments
            ADD CONSTRAINT chk_installments_value
            CHECK (value > 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE installments
            ADD CONSTRAINT chk_installments_paid_consistency
            CHECK (
                (paid = TRUE AND paid_at IS NOT NULL)
                OR (paid = FALSE AND paid_at IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE installments DROP CONSTRAINT chk_installments_number");
            DB::statement("ALTER TABLE installments DROP CONSTRAINT chk_installments_value");
            DB::statement("ALTER TABLE installments DROP CONSTRAINT chk_installments_paid_consistency");
        }

        Schema::dropIfExists('installments');
    }
};
