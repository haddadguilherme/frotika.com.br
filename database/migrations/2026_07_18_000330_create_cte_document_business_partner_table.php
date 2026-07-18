<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cte_document_business_partner', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cte_document_id')->constrained('cte_documents')->cascadeOnDelete();
            $table->foreignId('business_partner_id')->constrained('business_partners')->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['cte_document_id', 'business_partner_id', 'role'], 'cte_partner_role_unique');
            $table->index('business_partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cte_document_business_partner');
    }
};
