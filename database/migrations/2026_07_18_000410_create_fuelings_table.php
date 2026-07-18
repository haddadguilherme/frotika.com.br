<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuelings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->unsignedBigInteger('trip_id')->nullable();
            // Posto: parceiro comercial (kind = gas_station).
            $table->foreignId('supplier_id')->nullable()->constrained('business_partners')->nullOnDelete();

            $table->timestamp('fueled_at');
            $table->unsignedInteger('odometer');
            $table->string('product', 20);
            $table->decimal('liters', 10, 3);
            $table->decimal('price_per_liter', 10, 3)->nullable();
            $table->bigInteger('total_cents');
            $table->boolean('full_tank')->default(false);
            $table->string('tank', 20)->default('main');

            $table->string('station_name', 120)->nullable();
            $table->string('station_city', 80)->nullable();
            $table->string('station_state', 2)->nullable();

            $table->string('invoice_number', 60)->nullable();
            $table->string('payment_method', 20)->default('cash');
            $table->string('receipt_path', 255)->nullable();

            // Calculados (regra 8): só entre dois full_tank do mesmo tanque.
            $table->unsignedInteger('km_since_last')->nullable();
            $table->decimal('km_per_liter', 6, 3)->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'vehicle_id', 'fueled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuelings');
    }
};
