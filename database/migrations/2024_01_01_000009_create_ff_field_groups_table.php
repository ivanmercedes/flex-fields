<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ff_field_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('entity_id')->constrained('ff_entities')->cascadeOnDelete();
            $table->string('label');
            $table->string('key');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_collapsible')->default(true);
            $table->boolean('is_collapsed')->default(false);
            $table->timestamps();

            $table->unique(['entity_id', 'key']);
        });

        Schema::table('ff_custom_fields', function (Blueprint $table) {
            $table->foreignId('field_group_id')
                ->nullable()
                ->after('entity_id')
                ->constrained('ff_field_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ff_custom_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('field_group_id');
        });

        Schema::dropIfExists('ff_field_groups');
    }
};
