<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable();

            $table->string('name', 120);
            $table->string('cpf', 11)->nullable();

            $table->string('cnh_number', 20)->nullable();
            $table->string('cnh_category', 3)->nullable();
            $table->date('cnh_expires_at')->nullable();

            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'cnh_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
