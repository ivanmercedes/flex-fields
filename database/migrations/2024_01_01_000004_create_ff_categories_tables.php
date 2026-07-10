<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ff_entity_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('entity_id')->constrained('ff_entities')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('ff_entity_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'entity_id', 'slug'], 'ff_ent_cat_tenant_ent_slug_unique');
        });

        Schema::create('ff_entity_record_category', function (Blueprint $table) {
            $table->foreignId('entity_record_id')->constrained('ff_entity_records')->cascadeOnDelete();
            $table->foreignId('entity_category_id')->constrained('ff_entity_categories')->cascadeOnDelete();

            $table->primary(['entity_record_id', 'entity_category_id'], 'ff_record_category_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_entity_record_category');
        Schema::dropIfExists('ff_entity_categories');
    }
};
