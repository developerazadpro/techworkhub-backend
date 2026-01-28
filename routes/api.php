<?php

use App\Http\Controllers\API\WorkJobController;
use App\Http\Controllers\API\LoginController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// PUBLIC (no token)
Route::post('/login', [LoginController::class, 'login']);

// PROTECTED (token required)
Route::middleware('auth:sanctum')->group(function() {
    Route::get('/user', [UserController::class, 'me']);

    // 
    // ---------------------------------Technician------------------------------------------------
    //
    Route::get('/work-jobs', [WorkJobController::class, 'index']);
    Route::get('/work-jobs/{id}', [WorkJobController::class, 'show']);
    Route::post('/work-jobs/{id}/accept', [WorkJobController::class, 'accept']);
    Route::patch('/work-jobs/{id}/status', [WorkJobController::class, 'updateStatus']);
    Route::get('/my-jobs', [WorkJobController::class, 'myJobs']);    

    // 
    // ---------------------------------Client----------------------------------------------------
    //
    Route::post('/work-jobs', [WorkJobController::class, 'store']);
    Route::put('/work-jobs/{id}', [WorkJobController::class, 'update']);
    Route::get('/client/my-jobs', [WorkJobController::class, 'clientJobs']);
    
});
