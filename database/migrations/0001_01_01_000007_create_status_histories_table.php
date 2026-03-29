<?php

use App\Domain\Operation\OperationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->unsignedBigInteger('changed_by_user_id');
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            // Foreign Keys
            $table->foreign('operation_id')
                ->references('id')->on('operations')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('changed_by_user_id')
                ->references('id')->on('users')
                ->onDelete('restrict')
                ->onUpdate('cascade');

            // Índices
            $table->index('operation_id');
            $table->index(['operation_id', 'changed_at'], 'idx_status_histories_operation_changed_at');
        });

        if (DB::getDriverName() !== 'mysql') {
            if (isset($this->command)) {
                $this->command->warn('Aviso: Você não está usando MySQL. Algumas constraints de CHECK podem falhar.');
            }

            return;
        }

        $allowedOperationStatus = OperationStatus::cases()
            |> (fn (array $cases) => array_map(fn ($status) => "'$status->value'", $cases))
            |> (fn (array $list) => implode(', ', $list));

        DB::statement(<<<SQL
            ALTER TABLE operation_status_histories
            ADD CONSTRAINT chk_status_histories_previous_status
            CHECK (
                previous_status IN ($allowedOperationStatus)
                OR previous_status IS NULL
            )
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operation_status_histories
            ADD CONSTRAINT chk_status_histories_new_status
            CHECK (
                new_status IN ($allowedOperationStatus)
            )
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE operation_status_histories
            ADD CONSTRAINT chk_status_histories_previous_new_distinct
            CHECK (
                previous_status IS NULL
                OR previous_status != new_status
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE operation_status_histories DROP CONSTRAINT chk_status_histories_previous_status");
            DB::statement("ALTER TABLE operation_status_histories DROP CONSTRAINT chk_status_histories_new_status");
            DB::statement("ALTER TABLE operation_status_histories DROP CONSTRAINT chk_status_histories_previous_new_distinct");
        }

        Schema::dropIfExists('operation_status_histories');
    }
};
