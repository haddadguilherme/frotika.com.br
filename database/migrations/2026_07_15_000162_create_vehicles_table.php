<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('plate', 8);
            $table->string('type', 20)->default('tractor');
            $table->string('status', 20)->default('active');
            $table->string('ownership', 20)->default('own');

            $table->string('brand', 60)->nullable();
            $table->string('model', 60)->nullable();
            $table->unsignedSmallInteger('year_manufacture')->nullable();
            $table->unsignedSmallInteger('year_model')->nullable();
            $table->string('renavam', 20)->nullable();
            $table->string('chassis', 30)->nullable();
            $table->string('rntrc', 12)->nullable();

            $table->unsignedTinyInteger('axles')->nullable();
            $table->string('body_type', 20)->nullable();
            $table->unsignedInteger('tare_kg')->nullable();
            $table->unsignedInteger('capacity_kg')->nullable();
            $table->decimal('capacity_m3', 10, 3)->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->unsignedInteger('tank_capacity_l')->nullable();

            $table->unsignedInteger('odometer_initial')->default(0);
            $table->unsignedInteger('odometer_current')->default(0);

            $table->date('acquisition_date')->nullable();
            $table->bigInteger('acquisition_value_cents')->nullable();
            $table->bigInteger('residual_value_cents')->nullable();
            $table->unsignedSmallInteger('depreciation_months')->nullable();

            $table->boolean('provisioned')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'plate']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
