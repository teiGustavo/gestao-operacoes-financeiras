<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ShowLoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OperationCsvImportController;
use App\Http\Controllers\OperationDetailsController;
use App\Http\Controllers\OperationInstallmentPaymentController;
use App\Http\Controllers\OperationListController;
use App\Http\Controllers\OperationReportCsvDownloadController;
use App\Http\Controllers\OperationReportCsvExportController;
use App\Http\Controllers\OperationRunsStatusController;
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
    Route::get('/operations/runs-status', OperationRunsStatusController::class)->name('operations.runs.status');
    Route::post('/operations/import/csv', OperationCsvImportController::class)->name('operations.import.csv');
    Route::get('/operations/report/csv', OperationReportCsvExportController::class)->name('operations.report.csv');
    Route::get('/operations/report/csv/download/{operationReportRun}', OperationReportCsvDownloadController::class)
        ->name('operations.report.csv.download');
    Route::get('/operations/{operation}', OperationDetailsController::class)->name('operations.show');
    Route::patch('/operations/{operation}/installments/{installment}/pay', OperationInstallmentPaymentController::class)
        ->name('operations.installments.pay');
    Route::patch('/operations/{operation}/status', OperationStatusController::class)->name('operations.status.update');
});
