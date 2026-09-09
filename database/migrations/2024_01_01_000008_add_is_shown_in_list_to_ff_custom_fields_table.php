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
            $table->boolean('is_shown_in_list')->default(false)->after('is_searchable');
        });
    }

    public function down(): void
    {
        Schema::table('ff_custom_fields', function (Blueprint $table) {
            $table->dropColumn('is_shown_in_list');
        });
    }
};
