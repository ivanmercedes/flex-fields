<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ff_entity_records', 'publish_at')) {
            return;
        }

        Schema::table('ff_entity_records', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->after('status');
            $table->index(['status', 'publish_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ff_entity_records', function (Blueprint $table) {
            $table->dropIndex(['status', 'publish_at']);
            $table->dropColumn('publish_at');
        });
    }
};
