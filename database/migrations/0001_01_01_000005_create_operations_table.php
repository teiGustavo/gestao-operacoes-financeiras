<?php

use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('agreement_id');
            $table->decimal('requested_value', 15, 2);
            $table->decimal('disbursement_value', 15, 2);
            $table->decimal('total_interest', 15, 2);
            $table->decimal('late_fee_rate', 5, 2);
            $table->decimal('late_interest_rate', 5, 2);
            $table->integer('installments_count');
            $table->integer('paid_installments_count')->default(0)->unsigned();
            $table->decimal('installment_value', 15, 2);
            $table->string('status', 30)->default(OperationStatus::DRAFT->value);
            $table->string('product_type', 20);
            $table->date('first_due_date');
            $table->date('proposal_created_date');
            $table->timestamp('payment_date')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('client_id')
                ->references('id')->on('clients')
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->foreign('agreement_id')
                ->references('id')->on('agreements')
                ->onDelete('restrict')
                ->onUpdate('cascade');

            // Índices
            $table->index('status');
            $table->index('product_type');
            $table->index('agreement_id');
            $table->index('client_id');
            $table->index(['status', 'product_type', 'agreement_id', 'id'], 'idx_operations_filters');
            $table->index(['status', 'proposal_created_date'], 'idx_operations_status_created');
            $table->index('payment_date');
        });

        if (DB::getDriverName() !== 'mysql') {
            if (isset($this->command)) {
                $this->command->warn('Aviso: Você não está usando MySQL. Algumas constraints de CHECK podem falhar.');
            }

            return;
        }

        if (!Schema::hasTable('operations')) {
            if (isset($this->command)) {
                $this->command->warn("Aviso: A tabela 'operations' não foi criada. Os CHECKs não serão criados.");
            }

            return;
        }

        $enumCasesToString = fn (array $cases) =>
            array_map(fn ($case) => "'$case->value'", $cases)
            |> (fn (array $list) => implode(', ', $list));

        $allowedOperationStatus = $enumCasesToString(OperationStatus::cases());
        $allowedProductTypes = $enumCasesToString(ProductType::cases());

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_status
            CHECK (status IN ($allowedOperationStatus))
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_product_type
            CHECK (product_type IN ($allowedProductTypes))
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_requested_value
            CHECK (requested_value > 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_disbursement_value
            CHECK (disbursement_value >= 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_total_interest
            CHECK (total_interest >= 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_late_fee_rate
            CHECK (late_fee_rate >= 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_late_interest_rate
            CHECK (late_interest_rate >= 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_installments_count
            CHECK (installments_count > 0)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_paid_installments_count
            CHECK (paid_installments_count >= 0 AND paid_installments_count <= installments_count)
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_installment_value
            CHECK (installment_value > 0)
        SQL);

        $operationStatusDisbursed = "'OperationStatus::DISBURSED->value'";

        DB::statement(<<<SQL
            ALTER TABLE operations ADD CONSTRAINT chk_operations_payment_date_status
            CHECK (
                (status = $operationStatusDisbursed AND payment_date IS NOT NULL)
               OR (status != $operationStatusDisbursed AND payment_date IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_status");
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_product_type");
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_requested_value");
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_disbursement_value");
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_total_interest");
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_late_fee_rate");
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_late_interest_rate");
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_installments_count");
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_paid_installments_count");
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_installment_value");
            DB::statement("ALTER TABLE operations DROP CONSTRAINT chk_operations_payment_date_status");
        }

        Schema::dropIfExists('operations');
    }
};
