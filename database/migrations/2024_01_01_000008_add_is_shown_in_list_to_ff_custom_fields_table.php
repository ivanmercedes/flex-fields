<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Apps that published an earlier copy of this migration already have the column.
        if (Schema::hasColumn('ff_custom_fields', 'is_shown_in_list')) {
            return;
        }

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
