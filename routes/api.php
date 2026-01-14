<?php

use App\Http\Controllers\WorkJobController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function() {
    Route::get('/work-jobs', [WorkJobController::class, 'index']);
    Route::post('/work-jobs', [WorkJobController::class, 'store']);
    Route::post('/work-jobs/{id}/accept', [WorkJobController::class, 'accept']);
});
