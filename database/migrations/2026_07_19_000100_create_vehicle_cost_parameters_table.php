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

            // Reservas por km: preço de reposição ÷ vida útil.
            $table->bigInteger('tire_set_price_cents')->nullable();
            $table->unsignedInteger('tire_life_km')->nullable();
            $table->bigInteger('oil_change_cost_cents')->nullable();
            $table->unsignedInteger('oil_interval_km')->nullable();

            // Reserva prudencial: % da receita líquida do período.
            $table->decimal('prudential_percent', 5, 2)->nullable();

            // Provisões mensais imputadas ao veículo.
            $table->bigInteger('driver_salary_cents')->nullable();
            $table->bigInteger('owner_prolabore_cents')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_cost_parameters');
    }
};
