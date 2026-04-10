<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ff_entity_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('ff_entities')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('status')->default('published'); // draft | published | archived
            $table->integer('order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'status']);
        });

        Schema::create('ff_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_record_id')
                ->constrained('ff_entity_records')
                ->cascadeOnDelete();
            $table->foreignId('custom_field_id')
                ->constrained('ff_custom_fields')
                ->cascadeOnDelete();
            $table->longText('value')->nullable();
            $table->timestamps();

            $table->unique(['entity_record_id', 'custom_field_id']);
            $table->index('custom_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_field_values');
        Schema::dropIfExists('ff_entity_records');
    }
};
