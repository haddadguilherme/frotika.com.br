<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_platform_admin')->default(false)->after('remember_token');
            $table->foreignId('current_group_id')->nullable()->after('is_platform_admin')->constrained('groups')->nullOnDelete();
            $table->foreignId('current_company_id')->nullable()->after('current_group_id')->constrained('companies')->nullOnDelete();
            $table->json('preferences')->nullable()->after('current_company_id');
            $table->timestamp('last_login_at')->nullable()->after('preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_company_id');
            $table->dropConstrainedForeignId('current_group_id');
            $table->dropColumn(['phone', 'is_platform_admin', 'preferences', 'last_login_at']);
        });
    }
};
