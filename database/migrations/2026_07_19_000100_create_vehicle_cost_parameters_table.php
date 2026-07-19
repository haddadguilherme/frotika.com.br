<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_cost_parameters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // vehicle_id nulo = padrão da empresa; preenchido = override do veículo.
            $table->foreignId('vehicle_id')->nullable()->constrained()->cascadeOnDelete();

            // Reservas em R$/km (fração de centavo por km — como preço/litro, decimal).
            $table->decimal('oil_reserve_per_km', 10, 4)->nullable();
            $table->decimal('tire_reserve_per_km', 10, 4)->nullable();
            $table->decimal('prudential_reserve_per_km', 10, 4)->nullable();

            // Salário do motorista: provisão mensal (motorista contratado).
            $table->bigInteger('driver_salary_cents')->nullable();

            // Pró-labore/retirada do dono: % da receita líquida.
            $table->decimal('prolabore_percent', 5, 2)->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_cost_parameters');
    }
};
