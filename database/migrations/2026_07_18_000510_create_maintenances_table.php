<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            // Oficina: parceiro comercial (kind = workshop).
            $table->foreignId('supplier_id')->nullable()->constrained('business_partners')->nullOnDelete();

            $table->string('type', 20)->default('corrective');
            $table->string('category', 20)->default('other');
            $table->string('status', 20)->default('open');

            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->unsignedInteger('odometer')->nullable();

            $table->string('workshop_name', 120)->nullable();
            $table->string('invoice_number', 60)->nullable();

            $table->bigInteger('labor_cents')->default(0);
            $table->bigInteger('parts_cents')->default(0);
            $table->bigInteger('total_cents')->default(0);

            $table->text('description')->nullable();
            $table->decimal('downtime_hours', 6, 2)->nullable();
            $table->unsignedInteger('next_service_odometer')->nullable();
            $table->date('next_service_at')->nullable();

            $table->string('attachment_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'vehicle_id', 'opened_at']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
