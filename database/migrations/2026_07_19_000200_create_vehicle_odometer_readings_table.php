<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_odometer_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            $table->date('read_on');
            $table->unsignedInteger('odometer');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable();

            $table->timestamps();

            // Uma leitura por veículo por dia (a última do dia manda no updateOrCreate).
            $table->unique(['company_id', 'vehicle_id', 'read_on']);
            $table->index(['company_id', 'vehicle_id', 'read_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_odometer_readings');
    }
};
