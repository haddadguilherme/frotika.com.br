<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 20)->default('cash');
            $table->string('bank_code', 10)->nullable();
            $table->string('agency', 20)->nullable();
            $table->string('number', 30)->nullable();
            $table->bigInteger('initial_balance_cents')->default(0);
            $table->date('initial_balance_at')->nullable();
            $table->bigInteger('current_balance_cents')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // MySQL não tem índice único parcial: coluna gerada só recebe valor
            // quando a conta é padrão e ativa; NULLs não colidem no índice único,
            // garantindo no máximo uma conta padrão por empresa.
            $table->unsignedTinyInteger('default_flag')
                ->virtualAs('case when is_default = 1 and deleted_at is null then 1 else null end')
                ->nullable();

            $table->index(['company_id', 'active']);
            $table->unique(['company_id', 'default_flag'], 'bank_accounts_default_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
