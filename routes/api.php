<?php

use App\Http\Controllers\API\WorkJobController;
use App\Http\Controllers\API\LoginController;
use Illuminate\Support\Facades\Route;

// PUBLIC (no token)
Route::post('/login', [LoginController::class, 'login']);

// PROTECTED (token required)
Route::middleware('auth:sanctum')->group(function() {
    Route::get('/work-jobs', [WorkJobController::class, 'index']);
    Route::post('/work-jobs', [WorkJobController::class, 'store']);
    Route::post('/work-jobs/{id}/accept', [WorkJobController::class, 'accept']);
});
