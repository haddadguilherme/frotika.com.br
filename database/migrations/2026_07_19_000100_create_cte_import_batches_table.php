<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cte_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('total_files');
            $table->unsignedSmallInteger('processed_files')->default(0);
            $table->unsignedSmallInteger('imported_count')->default(0);
            $table->unsignedSmallInteger('failed_count')->default(0);
            $table->string('status', 20)->default('processing');
            // Resultado por arquivo: [{file, status, message, cte_id, access_key}].
            $table->json('results')->nullable();
            $table->timestamps();

            $table->unique('uuid');
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cte_import_batches');
    }
};
