<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->date('crlv_due_at')->nullable()->after('acquisition_value_cents');
            $table->date('antt_due_at')->nullable()->after('crlv_due_at');
            $table->date('insurance_due_at')->nullable()->after('antt_due_at');
            $table->string('engine_number')->nullable()->after('rntrc');
            $table->decimal('axle_distance_m', 4, 2)->nullable()->after('axles');
            $table->unsignedTinyInteger('tire_count')->nullable()->after('axle_distance_m');
            $table->string('tire_size', 20)->nullable()->after('tire_count');
            $table->boolean('is_financed')->default(false)->after('insurance_due_at');
            $table->string('financing_type')->nullable()->after('is_financed');
            $table->string('creditor_name')->nullable()->after('financing_type');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn([
                'crlv_due_at',
                'antt_due_at',
                'insurance_due_at',
                'engine_number',
                'axle_distance_m',
                'tire_count',
                'tire_size',
                'is_financed',
                'financing_type',
                'creditor_name',
            ]);
        });
    }
};
