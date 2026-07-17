<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_category_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->string('type', 20);
            $table->string('description', 200);
            $table->string('document_number', 50)->nullable();
            $table->date('competence_date');
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->bigInteger('amount_cents');
            $table->string('status', 20);
            $table->string('payment_method', 30)->nullable();
            $table->nullableMorphs('sourceable');
            $table->foreignId('transfer_pair_id')->nullable()->constrained('financial_entries')->nullOnDelete();
            $table->unsignedBigInteger('recurrence_id')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'paid_at', 'status']);
            $table->index(['company_id', 'competence_date', 'vehicle_id']);
            $table->index(['company_id', 'sourceable_type', 'sourceable_id']);
            $table->index(['company_id', 'bank_account_id', 'paid_at']);
            $table->index(['company_id', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_entries');
    }
};
