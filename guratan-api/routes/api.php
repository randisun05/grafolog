<?php

use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\AspekController as AdminAspekController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\CompanyController;
use App\Http\Controllers\Api\Admin\ConceptMapController;
use App\Http\Controllers\Api\Admin\ContentBlockController as AdminContentBlockController;
use App\Http\Controllers\Api\Admin\DiscountCodeController;
use App\Http\Controllers\Api\Admin\IndikatorController as AdminIndikatorController;
use App\Http\Controllers\Api\Admin\IndikatorRuleController;
use App\Http\Controllers\Api\Admin\KombinasiSyaratController;
use App\Http\Controllers\Api\Admin\KombinasiTemuanController;
use App\Http\Controllers\Api\Admin\MeasurementCategoryController;
use App\Http\Controllers\Api\Admin\MeasurementVariableController as AdminMeasurementVariableController;
use App\Http\Controllers\Api\Admin\PricingController as AdminPricingController;
use App\Http\Controllers\Api\Admin\ScoringRuleBandController;
use App\Http\Controllers\Api\Admin\SindromController as AdminSindromController;
use App\Http\Controllers\Api\Admin\TokenCostController;
use App\Http\Controllers\Api\Admin\TokenPriceController as AdminTokenPriceController;
use App\Http\Controllers\Api\Admin\TopikController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Hr\CandidateImportController;
use App\Http\Controllers\Api\MeasurementController;
use App\Http\Controllers\Api\MeasurementVariableController;
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
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
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
    Route::post('/samples/{sample}/scores/correct', [ScoringController::class, 'correct']);
    Route::post('/samples/{sample}/payment', [PaymentController::class, 'store']);

    // KM-G: measurement worksheet + checklist Indikator - lihat CLAUDE.md.
    // Tidak mengubah ScoringController::submit di atas sama sekali - hasil
    // checklist ini diubah frontend jadi payload `skor` yang sama persis,
    // lalu POST ke endpoint yang sudah ada.
    Route::get('/measurement-variables', [MeasurementVariableController::class, 'index']);
    Route::get('/samples/{sample}/measurements', [MeasurementController::class, 'index']);
    Route::post('/samples/{sample}/measurements', [MeasurementController::class, 'store']);
    Route::get('/samples/{sample}/checklist', [ChecklistController::class, 'index']);
    Route::post('/samples/{sample}/checklist/toggle', [ChecklistController::class, 'toggle']);
    Route::middleware('role:hr,administrator')->post('/samples/{sample}/assignment', [AssignmentController::class, 'store']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{report}', [ReportController::class, 'show'])->middleware('log.report_access');
    Route::get('/reports/{report}/segmen', [ReportController::class, 'segmen']);
    Route::get('/reports/{report}/pdf', [ReportController::class, 'pdf'])->middleware('log.report_access');
    Route::patch('/reports/{report}/aspek/{kode}/narasi', [ReportController::class, 'updateNarasi']);
    // Throttle tambahan KHUSUS endpoint ini, di atas throttle:60,1 grup di
    // atas - itu terlalu longgar untuk endpoint yang memanggil Anthropic
    // (biaya nyata per klik). 20/jam per grafolog cukup untuk pemakaian
    // wajar (generate + beberapa kali regenerate wajar per laporan) tapi
    // menutup risiko klik berulang/force berulang membakar biaya tanpa
    // sengaja - lihat CLAUDE.md "Guard biaya AI".
    Route::post('/reports/{report}/narasi-terpadu/generate', [ReportController::class, 'generateNarasiTerpadu'])
        ->middleware('throttle:20,60');
    Route::patch('/reports/{report}/narasi-terpadu', [ReportController::class, 'updateNarasiTerpadu']);
    Route::get('/reports/{report}/revisions', [ReportController::class, 'revisions']);
    Route::get('/reports/{report}/revisions/{revision}', [ReportController::class, 'showRevision']);

    Route::get('/sindrom', [SindromController::class, 'index']);
    Route::middleware('throttle:15,1')->get('/users/lookup', [UserLookupController::class, 'byEmail']);
    Route::post('/clients', [UserLookupController::class, 'store']);
    Route::middleware('role:hr,administrator')->get('/grafologs', [UserLookupController::class, 'grafologs']);

    Route::middleware('role:administrator')->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::patch('/users/{user}', [AdminUserController::class, 'update']);
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::patch('/companies/{company}', [CompanyController::class, 'update']);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
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

        // Knowledge Management System (KM-B, 2026-08-08) - lihat CLAUDE.md
        Route::get('/knowledge/sindrom', [AdminSindromController::class, 'index']);
        Route::post('/knowledge/sindrom', [AdminSindromController::class, 'store']);
        Route::put('/knowledge/sindrom/{sindrom}', [AdminSindromController::class, 'update']);
        Route::delete('/knowledge/sindrom/{sindrom}', [AdminSindromController::class, 'destroy']);

        Route::get('/knowledge/measurement-variables', [AdminMeasurementVariableController::class, 'index']);
        Route::post('/knowledge/measurement-variables', [AdminMeasurementVariableController::class, 'store']);
        Route::put('/knowledge/measurement-variables/{measurementVariable}', [AdminMeasurementVariableController::class, 'update']);
        Route::delete('/knowledge/measurement-variables/{measurementVariable}', [AdminMeasurementVariableController::class, 'destroy']);
        Route::post('/knowledge/measurement-variables/{measurementVariable}/categories', [MeasurementCategoryController::class, 'store']);
        Route::put('/knowledge/measurement-categories/{measurementCategory}', [MeasurementCategoryController::class, 'update']);
        Route::delete('/knowledge/measurement-categories/{measurementCategory}', [MeasurementCategoryController::class, 'destroy']);

        Route::get('/knowledge/scoring-rule-bands', [ScoringRuleBandController::class, 'index']);
        Route::post('/knowledge/scoring-rule-bands', [ScoringRuleBandController::class, 'store']);
        Route::put('/knowledge/scoring-rule-bands/{scoringRuleBand}', [ScoringRuleBandController::class, 'update']);
        Route::delete('/knowledge/scoring-rule-bands/{scoringRuleBand}', [ScoringRuleBandController::class, 'destroy']);

        Route::get('/knowledge/aspek', [AdminAspekController::class, 'index']);
        Route::post('/knowledge/aspek', [AdminAspekController::class, 'store']);
        Route::put('/knowledge/aspek/{aspek}', [AdminAspekController::class, 'update']);
        Route::delete('/knowledge/aspek/{aspek}', [AdminAspekController::class, 'destroy']);
        Route::put('/knowledge/aspek/{aspek}/topik', [AdminAspekController::class, 'syncTopik']);

        Route::get('/knowledge/indikator', [AdminIndikatorController::class, 'index']);
        Route::get('/knowledge/indikator-options', [AdminIndikatorController::class, 'options']);
        Route::post('/knowledge/indikator', [AdminIndikatorController::class, 'store']);
        Route::put('/knowledge/indikator/{indikator}', [AdminIndikatorController::class, 'update']);
        Route::delete('/knowledge/indikator/{indikator}', [AdminIndikatorController::class, 'destroy']);
        Route::post('/knowledge/indikator/{indikator}/rules', [IndikatorRuleController::class, 'store']);
        Route::put('/knowledge/indikator-rules/{indikatorRule}', [IndikatorRuleController::class, 'update']);
        Route::delete('/knowledge/indikator-rules/{indikatorRule}', [IndikatorRuleController::class, 'destroy']);

        // KM-H (2026-08-08): peta konsep, murni baca - lihat CLAUDE.md.
        Route::get('/knowledge/concept-map', [ConceptMapController::class, 'overview']);
        Route::get('/knowledge/concept-map/aspek/{aspek}', [ConceptMapController::class, 'aspek']);
        Route::get('/knowledge/concept-map/indikator/{indikator}', [ConceptMapController::class, 'indikator']);

        // Kombinasi Temuan (2026-08-22) - lihat CLAUDE.md.
        Route::get('/knowledge/kombinasi', [KombinasiTemuanController::class, 'index']);
        Route::post('/knowledge/kombinasi', [KombinasiTemuanController::class, 'store']);
        Route::put('/knowledge/kombinasi/{kombinasiTemuan}', [KombinasiTemuanController::class, 'update']);
        Route::delete('/knowledge/kombinasi/{kombinasiTemuan}', [KombinasiTemuanController::class, 'destroy']);
        Route::post('/knowledge/kombinasi/{kombinasiTemuan}/syarat', [KombinasiSyaratController::class, 'store']);
        Route::delete('/knowledge/kombinasi-syarat/{kombinasiSyarat}', [KombinasiSyaratController::class, 'destroy']);
        Route::put('/knowledge/kombinasi/{kombinasiTemuan}/topik', [KombinasiTemuanController::class, 'syncTopik']);

        // Topik / kategorisasi (2026-08-22) - lihat CLAUDE.md "Topik (kategorisasi)".
        Route::get('/knowledge/topik', [TopikController::class, 'index']);
        Route::post('/knowledge/topik', [TopikController::class, 'store']);
        Route::put('/knowledge/topik/{topik}', [TopikController::class, 'update']);
        Route::delete('/knowledge/topik/{topik}', [TopikController::class, 'destroy']);
    });

    Route::middleware('role:hr')->prefix('hr')->group(function () {
        Route::post('/candidates/import', [CandidateImportController::class, 'import']);
    });
});
