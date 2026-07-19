<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_license_invoices', function (Blueprint $table): void {
            $table->string('boleto_file_path')->nullable()->after('boleto_pdf_url');
            $table->string('boleto_file_original_name', 255)->nullable()->after('boleto_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('group_license_invoices', function (Blueprint $table): void {
            $table->dropColumn(['boleto_file_path', 'boleto_file_original_name']);
        });
    }
};
