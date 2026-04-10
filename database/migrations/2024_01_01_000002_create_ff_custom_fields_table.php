<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ff_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('ff_entities')->cascadeOnDelete();
            $table->string('label');
            $table->string('key');
            $table->string('type')->default('text');
            $table->text('description')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('default_value')->nullable();
            $table->json('options')->nullable();           // for select / multiselect
            $table->json('validation_rules')->nullable();  // ['required', 'max:255']
            $table->json('settings')->nullable();          // type-specific extra config
            $table->integer('order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_shown_in_list')->default(true);
            $table->string('width')->default('full');     // full | half | third
            $table->timestamps();

            $table->unique(['entity_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_custom_fields');
    }
};
