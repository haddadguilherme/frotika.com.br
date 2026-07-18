<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document', 14)->nullable();
            $table->string('document_type', 10)->default('none');
            $table->string('legal_name', 150);
            $table->string('trade_name', 150)->nullable();
            $table->string('kind', 20)->default('other');
            $table->decimal('default_freight_share_percent', 5, 2)->nullable();
            $table->string('state_registration', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement', 80)->nullable();
            $table->string('district', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('ibge_code', 7)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'document']);
            $table->index(['company_id', 'kind']);
            $table->index(['company_id', 'legal_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_partners');
    }
};
