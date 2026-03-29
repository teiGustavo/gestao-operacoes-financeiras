<?php

use App\Domain\Client\ClientGender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('cpf', 14)->unique();
            $table->date('birth_date');
            $table->string('gender', 20)->default(ClientGender::PREFER_NOT_TO_SAY->value);
            $table->string('email', 255)->unique();
            $table->timestamps();

            // Índices
            $table->index('cpf');
            $table->index('email');
        });

        if (DB::getDriverName() !== 'mysql') {
            if (isset($this->command)) {
                $this->command->warn('Aviso: Você não está usando MySQL. Algumas constraints de CHECK podem falhar.');
            }

            return;
        }

        if (!Schema::hasTable('clients')) {
            if (isset($this->command)) {
                $this->command->warn("Aviso: A tabela 'clients' não foi criada. Os CHECKs não serão criados.");
            }

            return;
        }

        $allowedGenders = ClientGender::cases()
            |> (fn (array $list) => array_map(fn ($case) => "'$case->value'", $list))
            |> (fn (array $list) => implode(', ', $list));

        DB::statement(<<< SQL
            ALTER TABLE clients
            ADD CONSTRAINT chk_client_gender CHECK (gender IN ($allowedGenders));
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE clients
            ADD CONSTRAINT chk_client_email_format CHECK (email LIKE '%@%.%')
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE clients DROP CONSTRAINT chk_client_gender");
            DB::statement("ALTER TABLE clients DROP CONSTRAINT chk_client_email_format");
        }

        Schema::dropIfExists('clients');
    }
};
