<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkJobController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Work Jobs
    Route::get('/work-jobs', [WorkJobController::class, 'index']);
    Route::post('/work-jobs', [WorkJobController::class, 'store']);
    Route::post('/work-jobs/{id}/accept', [WorkJobController::class, 'accept']);
});

require __DIR__.'/auth.php';
