<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ShowLoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OperationDetailsController;
use App\Http\Controllers\OperationListController;
use App\Http\Controllers\OperationStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', ShowLoginController::class)->name('login');
    Route::post('/login', LoginController::class)->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::get('/operations', OperationListController::class)->name('operations.index');
    Route::get('/operations/{operation}', OperationDetailsController::class)->name('operations.show');
    Route::patch('/operations/{operation}/status', OperationStatusController::class)->name('operations.status.update');
});
