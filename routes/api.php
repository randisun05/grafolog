<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SampleController;
use App\Http\Controllers\Api\ScoringController;
use App\Http\Controllers\Api\SindromController;
use App\Http\Controllers\Api\UserLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// DOKU memanggil ini server-to-server, tidak punya token Sanctum kita -
// keamanan bergantung penuh pada DokuService::verifyNotificationSignature().
Route::middleware('throttle:30,1')->post('/payments/notification', [PaymentController::class, 'notification']);

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/samples', [SampleController::class, 'index']);
    Route::post('/samples', [SampleController::class, 'store']);
    Route::get('/samples/{sample}', [SampleController::class, 'show']);
    Route::post('/samples/{sample}/scores', [ScoringController::class, 'submit']);
    Route::post('/samples/{sample}/payment', [PaymentController::class, 'store']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{report}', [ReportController::class, 'show'])->middleware('log.report_access');
    Route::get('/reports/{report}/pdf', [ReportController::class, 'pdf'])->middleware('log.report_access');

    Route::get('/sindrom', [SindromController::class, 'index']);
    Route::middleware('throttle:15,1')->get('/users/lookup', [UserLookupController::class, 'byEmail']);
});
