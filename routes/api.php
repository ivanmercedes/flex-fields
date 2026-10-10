<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use IvanMercedes\FlexFields\Http\Controllers\EntityApiController;
use IvanMercedes\FlexFields\Http\Controllers\RecordApiController;

Route::prefix(config('flex-fields.api.prefix', 'api/flex'))
    ->middleware(config('flex-fields.api.middleware', ['api', 'auth:sanctum']))
    ->name('flex-fields.api.')
    ->group(function () {
        Route::get('entities', [EntityApiController::class, 'index'])->name('entities.index');
        Route::get('{entity}/schema', [EntityApiController::class, 'schema'])->name('entities.schema');

        Route::get('{entity}', [RecordApiController::class, 'index'])->name('records.index');
        Route::post('{entity}', [RecordApiController::class, 'store'])->name('records.store');
        Route::get('{entity}/{record}', [RecordApiController::class, 'show'])->name('records.show');
        Route::match(['put', 'patch'], '{entity}/{record}', [RecordApiController::class, 'update'])->name('records.update');
        Route::delete('{entity}/{record}', [RecordApiController::class, 'destroy'])->name('records.destroy');
        Route::post('{entity}/{record}/restore', [RecordApiController::class, 'restore'])->name('records.restore');
    });
