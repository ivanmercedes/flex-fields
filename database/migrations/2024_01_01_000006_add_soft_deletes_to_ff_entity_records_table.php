<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ff_entity_records', function (Blueprint $table) {
            $table->softDeletes()->index();
            $table->unique(['tenant_id', 'entity_id', 'slug'], 'ff_records_tenant_ent_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ff_entity_records', function (Blueprint $table) {
            $table->dropUnique('ff_records_tenant_ent_slug_unique');
            $table->dropSoftDeletes();
        });
    }
};
