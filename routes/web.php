<?php

use App\Http\Controllers\AutodeskController;
use App\Http\Controllers\PoolingStatusController;
use App\Http\Controllers\PowerBiConnectionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

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

    Route::get('/connection-api', [AutodeskController::class, 'connectionApi'])->name('connection-api');
    Route::get('/retrieve-access-token', [AutodeskController::class, 'retrieveAccessToken'])->name('retrieve-access-token');
    Route::get('/create-allow-url-autodesk', [AutodeskController::class, 'createAllowUrlAutodesk'])->name('create-allow-url-autodesk');
    Route::post('/authorization', [AutodeskController::class, 'authorization'])->name('authorization');

    Route::post('/autodesk-api-pooling', [AutodeskController::class, 'autodeskApiPooling'])->name('autodesk-api-pooling');
    Route::resource('/pooling-api', PoolingStatusController::class);
    Route::resource('/power-bi-connection', PowerBiConnectionController::class);
    Route::get('/get-refresh-history', [PowerBiConnectionController::class, 'getValidAccessToken'])->name('get-refresh-history');
    Route::get('/get-power-bi-authentication', [AutodeskController::class, 'getPowerBiAuthentication'])->name('get-power-bi-authentication');
    Route::get('/auth/microsoft/redirect', [PowerBiConnectionController::class, 'redirectToMicrosoft'])->name('auth.microsoft.redirect');
    Route::get('/auth/microsoft/callback', [PowerBiConnectionController::class, 'handleMicrosoftCallback'])->name('auth.microsoft.callback');

});

Route::get('/power-bi-dashboard', [PowerBiConnectionController::class, 'index'])->name('power-bi-dashboard');

require __DIR__.'/auth.php';
