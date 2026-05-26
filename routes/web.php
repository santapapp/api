<?php

use App\Http\Controllers\ApiInfoController;
use Illuminate\Support\Facades\Route;

// Root: JSON API info card (bukan redirect ke docs)
Route::get('/', [ApiInfoController::class, 'index']);

// Health check
Route::get('/health', [ApiInfoController::class, 'health']);

// Scramble docs hub (HTML)
Route::get('/docs/api', function () {
    return view('api-docs-hub');
});
