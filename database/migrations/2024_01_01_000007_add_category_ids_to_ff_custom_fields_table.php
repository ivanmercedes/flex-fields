<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ff_custom_fields', function (Blueprint $table) {
            $table->jsonb('category_ids')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('ff_custom_fields', function (Blueprint $table) {
            $table->dropColumn('category_ids');
        });
    }
};
