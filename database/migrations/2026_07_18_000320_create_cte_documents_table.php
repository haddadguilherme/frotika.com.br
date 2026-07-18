<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cte_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->char('access_key', 44);
            $table->string('layout_version', 6)->nullable();
            $table->unsignedSmallInteger('model')->nullable();
            $table->unsignedInteger('number');
            $table->unsignedSmallInteger('series');
            $table->string('cte_type', 20);
            $table->string('service_type', 20);
            $table->string('modal', 2)->nullable();
            $table->string('cfop', 10)->nullable();
            $table->string('operation_nature', 120)->nullable();
            $table->dateTime('issued_at');

            $table->string('issuer_document', 14)->nullable();
            $table->string('issuer_name', 150)->nullable();

            $table->string('origin_city', 80)->nullable();
            $table->string('origin_state', 2)->nullable();
            $table->string('origin_ibge', 7)->nullable();
            $table->string('destination_city', 80)->nullable();
            $table->string('destination_state', 2)->nullable();
            $table->string('destination_ibge', 7)->nullable();

            $table->string('taker_role', 20)->nullable();
            $table->string('taker_document', 14)->nullable();
            $table->string('taker_name', 150)->nullable();
            $table->string('sender_document', 14)->nullable();
            $table->string('sender_name', 150)->nullable();
            $table->string('recipient_document', 14)->nullable();
            $table->string('recipient_name', 150)->nullable();

            $table->bigInteger('total_value_cents')->default(0);
            $table->bigInteger('receivable_value_cents')->default(0);
            $table->bigInteger('icms_value_cents')->default(0);
            $table->bigInteger('cargo_value_cents')->nullable();
            $table->decimal('cargo_weight_kg', 12, 3)->nullable();
            $table->string('cargo_description', 255)->nullable();

            $table->decimal('applied_share_percent', 5, 2)->default(100);

            $table->string('rntrc', 12)->nullable();
            $table->char('referenced_key', 44)->nullable();
            $table->string('status', 20)->default('authorized');
            $table->string('protocol_number', 20)->nullable();

            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('trailer_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            // Snapshot do XML: mantido mesmo com o vínculo, para auditoria da origem.
            $table->string('driver_name', 150)->nullable();
            $table->string('driver_cpf', 11)->nullable();

            $table->string('xml_path')->nullable();
            $table->char('xml_hash', 64)->nullable();
            $table->json('raw')->nullable();

            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'access_key']);
            $table->index(['company_id', 'issued_at']);
            $table->index(['company_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cte_documents');
    }
};
