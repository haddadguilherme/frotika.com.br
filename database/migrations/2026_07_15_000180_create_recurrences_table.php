<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_category_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('description', 200);
            $table->string('document_number', 50)->nullable();
            $table->bigInteger('amount_cents');
            $table->string('frequency', 20);
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('installments')->nullable();
            $table->unsignedInteger('installments_generated')->default(0);
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'active', 'frequency']);
            $table->index(['company_id', 'starts_at', 'ends_at']);
            $table->index(['company_id', 'financial_category_id']);
            $table->index(['company_id', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrences');
    }
};
