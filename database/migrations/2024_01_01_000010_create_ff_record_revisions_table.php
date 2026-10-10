<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ff_record_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('entity_record_id')->constrained('ff_entity_records')->cascadeOnDelete();
            $table->string('event', 32);                 // created | updated | deleted | restored | reverted
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();     // snapshot of the causer display name
            $table->jsonb('snapshot');                   // full record state after the event
            $table->jsonb('diff')->nullable();           // {key: {old, new}} diff against previous revision
            $table->timestamp('created_at')->nullable();

            $table->index(['entity_record_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ff_record_revisions');
    }
};
