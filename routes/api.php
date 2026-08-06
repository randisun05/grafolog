<?php

use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\CompanyController;
use App\Http\Controllers\Api\Admin\ContentBlockController as AdminContentBlockController;
use App\Http\Controllers\Api\Admin\DiscountCodeController;
use App\Http\Controllers\Api\Admin\PricingController as AdminPricingController;
use App\Http\Controllers\Api\Admin\TokenCostController;
use App\Http\Controllers\Api\Admin\TokenPriceController as AdminTokenPriceController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Hr\CandidateImportController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SampleController;
use App\Http\Controllers\Api\ScoringController;
use App\Http\Controllers\Api\SindromController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\TokenPurchaseController;
use App\Http\Controllers\Api\UserLookupController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// DOKU memanggil ini server-to-server, tidak punya token Sanctum kita -
// keamanan bergantung penuh pada DokuService::verifyNotificationSignature().
Route::middleware('throttle:30,1')->post('/payments/notification', [PaymentController::class, 'notification']);

// Publik (tanpa login) - dipakai halaman harga/marketing sebelum checkout.
Route::get('/pricing', [PricingController::class, 'index']);
Route::get('/content', [ContentController::class, 'index']);

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::post('/pricing/preview', [PricingController::class, 'preview']);
    Route::get('/announcements', [AnnouncementController::class, 'index']);

    Route::get('/tokens/price', [TokenController::class, 'price']);
    Route::middleware('role:grafolog')->group(function () {
        Route::get('/tokens/wallet', [TokenController::class, 'wallet']);
        Route::post('/tokens/preview', [TokenController::class, 'preview']);
        Route::post('/tokens/purchase', [TokenPurchaseController::class, 'store']);
    });

    Route::get('/samples', [SampleController::class, 'index']);
    Route::post('/samples', [SampleController::class, 'store']);
    Route::get('/samples/{sample}', [SampleController::class, 'show']);
    Route::post('/samples/{sample}/scores/preview', [ScoringController::class, 'preview']);
    Route::post('/samples/{sample}/scores', [ScoringController::class, 'submit']);
    Route::post('/samples/{sample}/payment', [PaymentController::class, 'store']);
    Route::middleware('role:hr,administrator')->post('/samples/{sample}/assignment', [AssignmentController::class, 'store']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{report}', [ReportController::class, 'show'])->middleware('log.report_access');
    Route::get('/reports/{report}/pdf', [ReportController::class, 'pdf'])->middleware('log.report_access');

    Route::get('/sindrom', [SindromController::class, 'index']);
    Route::middleware('throttle:15,1')->get('/users/lookup', [UserLookupController::class, 'byEmail']);
    Route::middleware('role:hr,administrator')->get('/grafologs', [UserLookupController::class, 'grafologs']);

    Route::middleware('role:administrator')->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::get('/pricing', [AdminPricingController::class, 'index']);
        Route::put('/pricing/{tier}', [AdminPricingController::class, 'update']);
        Route::get('/token-price', [AdminTokenPriceController::class, 'index']);
        Route::put('/token-price', [AdminTokenPriceController::class, 'update']);
        Route::get('/token-costs', [TokenCostController::class, 'index']);
        Route::put('/token-costs/{tier}', [TokenCostController::class, 'update']);
        Route::get('/discount-codes', [DiscountCodeController::class, 'index']);
        Route::post('/discount-codes', [DiscountCodeController::class, 'store']);
        Route::patch('/discount-codes/{discountCode}', [DiscountCodeController::class, 'update']);
        Route::get('/content', [AdminContentBlockController::class, 'index']);
        Route::put('/content/{key}', [AdminContentBlockController::class, 'update']);
        Route::get('/announcements', [AdminAnnouncementController::class, 'index']);
        Route::post('/announcements', [AdminAnnouncementController::class, 'store']);
        Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update']);
    });

    Route::middleware('role:hr')->prefix('hr')->group(function () {
        Route::post('/candidates/import', [CandidateImportController::class, 'import']);
    });
});
