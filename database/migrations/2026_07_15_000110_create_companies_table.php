<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->char('cnpj', 14)->unique();
            $table->string('legal_name', 150);
            $table->string('trade_name', 150);
            $table->string('state_registration', 20)->nullable();
            $table->string('rntrc', 12)->nullable();
            $table->string('tax_regime', 20)->default('simples');
            $table->string('zip_code', 10)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement', 80)->nullable();
            $table->string('district', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('ibge_code', 7)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('logo_path')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
