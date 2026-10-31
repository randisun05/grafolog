<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AffiliateAdminController;
use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\ArticleAdminController;
use App\Http\Controllers\Api\Admin\AspekController as AdminAspekController;
use App\Http\Controllers\Api\Admin\BroadcastController;
use App\Http\Controllers\Api\Admin\ClientOrderController;
use App\Http\Controllers\Api\Admin\CompanyController;
use App\Http\Controllers\Api\Admin\CompanyVerificationController;
use App\Http\Controllers\Api\Admin\ConceptMapController;
use App\Http\Controllers\Api\Admin\ContentBlockController as AdminContentBlockController;
use App\Http\Controllers\Api\Admin\CvManagementController;
use App\Http\Controllers\Api\Admin\DiscountCodeController;
use App\Http\Controllers\Api\Admin\GrafologVerificationController;
use App\Http\Controllers\Api\Admin\IndikatorController as AdminIndikatorController;
use App\Http\Controllers\Api\Admin\IndikatorRuleController;
use App\Http\Controllers\Api\Admin\KnowledgeMatrixController;
use App\Http\Controllers\Api\Admin\LlmUsageController;
use App\Http\Controllers\Api\Admin\MeasurementCategoryController;
use App\Http\Controllers\Api\Admin\MeasurementVariableController as AdminMeasurementVariableController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PricingController as AdminPricingController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\RefundController;
use App\Http\Controllers\Api\Admin\ScoringRuleBandController;
use App\Http\Controllers\Api\Admin\SelfCheckAdminController;
use App\Http\Controllers\Api\Admin\SindromController as AdminSindromController;
use App\Http\Controllers\Api\Admin\TokenCostController;
use App\Http\Controllers\Api\Admin\TokenPriceController as AdminTokenPriceController;
use App\Http\Controllers\Api\Admin\ValidationIndexController;
use App\Http\Controllers\Api\Admin\VerificationCountsController;
use App\Http\Controllers\Api\AffiliateController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\Auth\CompanyRegistrationController;
use App\Http\Controllers\Api\Auth\GrafologRegistrationController;
use App\Http\Controllers\Api\Auth\PasswordChangeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CandidateTaskController;
use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\CvController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GrafologAvailabilityController;
use App\Http\Controllers\Api\GrafologDirectoryController;
use App\Http\Controllers\Api\GrafologRequestController;
use App\Http\Controllers\Api\Hr\B2bOrderController;
use App\Http\Controllers\Api\Hr\CandidateAnalysisController;
use App\Http\Controllers\Api\Hr\CandidateController;
use App\Http\Controllers\Api\Hr\CandidateImportController;
use App\Http\Controllers\Api\Hr\PositionController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\MeasurementController;
use App\Http\Controllers\Api\MeasurementVariableController;
use App\Http\Controllers\Api\OrderChatController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportValidationController;
use App\Http\Controllers\Api\SampleController;
use App\Http\Controllers\Api\SampleImageController;
use App\Http\Controllers\Api\ScoringController;
use App\Http\Controllers\Api\SelfCheckController;
use App\Http\Controllers\Api\ServiceDeliveryController;
use App\Http\Controllers\Api\SindromController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\TokenPurchaseController;
use App\Http\Controllers\Api\UserLookupController;
use App\Services\Payment\PaymentMethods;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register-grafolog', [GrafologRegistrationController::class, 'store']);
    Route::post('/auth/register-company', [CompanyRegistrationController::class, 'store']);
});

Route::middleware('throttle:password-reset')->group(function () {
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgot']);
    Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);
});

// DOKU memanggil ini server-to-server, tidak punya token Sanctum kita -
// keamanan bergantung penuh pada DokuService::verifyNotificationSignature().
Route::middleware('throttle:30,1')->post('/payments/notification', [PaymentController::class, 'notification']);
// Xendit: Callback URL invoice - diverifikasi header X-Callback-Token (XenditService::verifyWebhookToken()).
Route::middleware('throttle:30,1')->post('/payments/xendit-notification', [PaymentController::class, 'xenditNotification']);
Route::get('/payment-gateways', [PaymentController::class, 'gateways']);

// Publik (tanpa login) - dipakai halaman harga/marketing sebelum checkout.
Route::get('/pricing', [PricingController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{kode}', [ProductController::class, 'show']);
Route::get('/promos', [ProductController::class, 'promos']);
Route::get('/payment-methods', fn () => response()->json(PaymentMethods::groups()));
Route::get('/content', [ContentController::class, 'index']);

// Fase E (2026-10-17): cek tulisan terpandu (lead magnet), afiliasi, artikel, kuis - publik.
Route::get('/self-check', [SelfCheckController::class, 'index']);
Route::middleware('throttle:10,1')->post('/self-check', [SelfCheckController::class, 'store']);
Route::get('/self-check/{uuid}', [SelfCheckController::class, 'show'])->whereUuid('uuid');
Route::get('/affiliate-products', [AffiliateController::class, 'index']);
Route::middleware('throttle:30,1')->post('/affiliate-products/{product}/click', [AffiliateController::class, 'click']);
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);
Route::middleware('throttle:30,1')->get('/games/quiz', [GameController::class, 'quiz']);

// 120/menit: halaman HR & SampleImages memuat pratinjau per scan (1 GET per gambar).
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::middleware('throttle:10,1')->put('/auth/change-password', [PasswordChangeController::class, 'update']);
    Route::post('/me/accept-terms', [AccountController::class, 'acceptTerms']);
    Route::middleware('throttle:data-export')->get('/me/export', [AccountController::class, 'export']);
    Route::middleware('throttle:account-delete')->post('/me/delete', [AccountController::class, 'destroy']);
    Route::get('/cv', [CvController::class, 'mine']);
    Route::put('/cv', [CvController::class, 'updateMine']);
    Route::middleware('throttle:cv-upload')->post('/cv/files/{key}', [CvController::class, 'fileStoreMine']);
    Route::delete('/cv/files/{key}', [CvController::class, 'fileDestroyMine']);
    Route::get('/cv/users/{user}', [CvController::class, 'show']);
    Route::get('/cv/users/{user}/files/{key}', [CvController::class, 'fileShow']);
    Route::middleware(['role:hr', 'throttle:cv-upload'])->post('/cv/users/{user}/files/{key}', [CvController::class, 'fileStore']);
    Route::middleware('role:hr')->delete('/cv/users/{user}/files/{key}', [CvController::class, 'fileDestroy']);
    Route::middleware('role:hr')->put('/cv/users/{user}', [CvController::class, 'update']);
    Route::middleware('role:user')->get('/candidate/tasks', [CandidateTaskController::class, 'index']);
    Route::get('/inbox', [InboxController::class, 'index']);
    Route::get('/inbox/unread-count', [InboxController::class, 'unreadCount']);
    Route::post('/inbox/read-all', [InboxController::class, 'readAll']);
    Route::post('/inbox/{message}/read', [InboxController::class, 'read']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::post('/pricing/preview', [PricingController::class, 'preview']);

    // Pesanan produk & layanan pendukung (2026-10-17).
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/preview', [OrderController::class, 'preview']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{order}/payment', [OrderController::class, 'pay']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/grafolog', [OrderController::class, 'chooseGrafolog']);
    Route::get('/orders/{order}/messages', [OrderChatController::class, 'index']);
    Route::middleware('throttle:chat')->post('/orders/{order}/messages', [OrderChatController::class, 'store']);
    Route::get('/chat/unread', [OrderChatController::class, 'unread']);
    Route::middleware('role:user')->get('/grafologs/directory', [GrafologDirectoryController::class, 'index']);
    Route::middleware('role:grafolog')->group(function () {
        Route::get('/grafolog/requests', [GrafologRequestController::class, 'index']);
        Route::post('/grafolog/requests/{grafologRequest}/respond', [GrafologRequestController::class, 'respond']);
    });
    Route::get('/service-deliveries', [ServiceDeliveryController::class, 'index']);
    Route::get('/service-deliveries/{delivery}', [ServiceDeliveryController::class, 'show']);
    Route::post('/service-deliveries/{delivery}/assign', [ServiceDeliveryController::class, 'assign']);
    Route::put('/service-deliveries/{delivery}/schedule', [ServiceDeliveryController::class, 'schedule']);
    Route::post('/service-deliveries/{delivery}/report', [ServiceDeliveryController::class, 'report']);
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
    Route::get('/samples/{sample}/images', [SampleImageController::class, 'index']);
    Route::middleware('throttle:20,1')->post('/samples/{sample}/images', [SampleImageController::class, 'store']);
    Route::get('/samples/{sample}/images/{image}', [SampleImageController::class, 'show']);
    Route::delete('/samples/{sample}/images/{image}', [SampleImageController::class, 'destroy']);
    Route::post('/samples/{sample}/scores/preview', [ScoringController::class, 'preview']);
    Route::post('/samples/{sample}/scores', [ScoringController::class, 'submit']);
    Route::post('/samples/{sample}/scores/correct', [ScoringController::class, 'correct']);
    Route::post('/samples/{sample}/payment', [PaymentController::class, 'store']);
    Route::put('/samples/{sample}/consultation', [ConsultationController::class, 'upsert']);

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
    // Validasi supervisor opsional (Fase D, 2026-10-17) - grafolog penanganan.
    Route::middleware('role:grafolog')->group(function () {
        Route::get('/grafolog/availability', [GrafologAvailabilityController::class, 'index']);
        Route::put('/grafolog/availability', [GrafologAvailabilityController::class, 'update']);
        Route::get('/report-validation/fee', [ReportValidationController::class, 'fee']);
        Route::get('/report-validation/validators', [ReportValidationController::class, 'validators']);
        Route::get('/validations', [ReportValidationController::class, 'index']);
        Route::post('/validations/{validation}/decide', [ReportValidationController::class, 'decide']);
        Route::post('/validations/{validation}/decline', [ReportValidationController::class, 'decline']);
        Route::post('/validations/{validation}/confirm', [ReportValidationController::class, 'confirm']);
        Route::post('/reports/{report}/publish', [ReportValidationController::class, 'publish']);
        Route::post('/reports/{report}/request-validation', [ReportValidationController::class, 'request']);
    });
    Route::get('/reports/{report}/validations', [ReportValidationController::class, 'forReport']);
    Route::get('/reports/{report}', [ReportController::class, 'show'])->middleware('log.report_access');
    Route::get('/reports/{report}/pdf', [ReportController::class, 'pdf'])->middleware('log.report_access');
    Route::get('/reports/{report}/resume', [ReportController::class, 'resume'])->middleware('log.report_access');
    Route::patch('/reports/{report}/aspek/{kode}/narasi', [ReportController::class, 'updateNarasi']);
    Route::get('/reports/{report}/revisions', [ReportController::class, 'revisions']);
    Route::get('/reports/{report}/revisions/{revision}', [ReportController::class, 'showRevision']);

    Route::get('/sindrom', [SindromController::class, 'index']);
    Route::middleware('throttle:15,1')->get('/users/lookup', [UserLookupController::class, 'byEmail']);
    Route::get('/clients', [UserLookupController::class, 'index']);
    Route::post('/clients', [UserLookupController::class, 'store']);
    Route::middleware('throttle:10,1')->post('/clients/{client}/invite', [UserLookupController::class, 'resendInvitation']);
    Route::middleware('role:hr,administrator')->get('/grafologs', [UserLookupController::class, 'grafologs']);

    Route::middleware('role:administrator')->prefix('admin')->group(function () {
        // Kelola Produk (2026-10-17) - harga lewat PUT /admin/pricing/{kode}.
        Route::get('/products', [AdminProductController::class, 'index']);
        Route::get('/products/templates', [AdminProductController::class, 'templates']);
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::put('/products/{product}', [AdminProductController::class, 'update']);
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
        Route::post('/products/{product}/components', [AdminProductController::class, 'storeComponent']);
        Route::put('/product-components/{component}', [AdminProductController::class, 'updateComponent']);
        Route::delete('/product-components/{component}', [AdminProductController::class, 'destroyComponent']);

        // Verifikasi pendaftar grafolog (2026-10-17).
        Route::get('/grafolog-applications', [GrafologVerificationController::class, 'index']);
        Route::get('/grafolog-applications/{user}/certificate', [GrafologVerificationController::class, 'certificate']);
        Route::post('/grafolog-applications/{user}/decision', [GrafologVerificationController::class, 'decide']);
        Route::get('/verification-counts', VerificationCountsController::class);
        Route::get('/broadcasts', [BroadcastController::class, 'index']);
        Route::post('/broadcasts', [BroadcastController::class, 'store']);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/company-applications', [CompanyVerificationController::class, 'index']);
        Route::get('/validations', [ValidationIndexController::class, 'index']);
        Route::get('/refunds', [RefundController::class, 'index']);
        Route::post('/orders/{order}/refund', [RefundController::class, 'refund']);
        Route::get('/self-check', [SelfCheckAdminController::class, 'index']);
        Route::post('/self-check/questions', [SelfCheckAdminController::class, 'storeQuestion']);
        Route::put('/self-check/questions/{question}', [SelfCheckAdminController::class, 'updateQuestion']);
        Route::put('/self-check/options/{option}', [SelfCheckAdminController::class, 'updateOption']);
        Route::get('/self-check/results', [SelfCheckAdminController::class, 'results']);
        Route::get('/affiliate-products', [AffiliateAdminController::class, 'index']);
        Route::post('/affiliate-products', [AffiliateAdminController::class, 'store']);
        Route::put('/affiliate-products/{product}', [AffiliateAdminController::class, 'update']);
        Route::get('/articles', [ArticleAdminController::class, 'index']);
        Route::post('/articles', [ArticleAdminController::class, 'store']);
        Route::put('/articles/{article}', [ArticleAdminController::class, 'update']);
        Route::post('/company-applications/{company}/decision', [CompanyVerificationController::class, 'decide']);

        Route::get('/orders', [AdminOrderController::class, 'index']);
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
        Route::get('/client-orders', [ClientOrderController::class, 'index']);
        Route::post('/client-orders/{order}/assign', [ClientOrderController::class, 'assign']);
        Route::get('/llm-usage', [LlmUsageController::class, 'index']);
        Route::get('/cv', [CvManagementController::class, 'index']);
        Route::post('/cv/groups', [CvManagementController::class, 'storeGroup']);
        Route::put('/cv/groups/{group}', [CvManagementController::class, 'updateGroup']);
        Route::delete('/cv/groups/{group}', [CvManagementController::class, 'destroyGroup']);
        Route::post('/cv/fields', [CvManagementController::class, 'storeField']);
        Route::put('/cv/fields/{field}', [CvManagementController::class, 'updateField']);
        Route::delete('/cv/fields/{field}', [CvManagementController::class, 'destroyField']);
        Route::post('/cv/reorder', [CvManagementController::class, 'reorder']);
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

        // Matriks keterkaitan (2026-10-16): struktur aturan + korelasi empiris, murni baca.
        Route::get('/knowledge/matrix', [KnowledgeMatrixController::class, 'struktur']);
        Route::get('/knowledge/matrix/empiris', [KnowledgeMatrixController::class, 'empiris']);
    });

    Route::middleware('role:hr')->prefix('hr')->group(function () {
        Route::post('/candidates/import', [CandidateImportController::class, 'import']);

        // Fase C B2B (2026-10-17): posisi, kandidat, pesanan batch, analisis.
        Route::get('/positions', [PositionController::class, 'index']);
        Route::post('/positions', [PositionController::class, 'store']);
        Route::put('/positions/{position}', [PositionController::class, 'update']);
        Route::get('/candidates', [CandidateController::class, 'index']);
        Route::post('/candidates', [CandidateController::class, 'store']);
        Route::put('/candidates/{candidate}', [CandidateController::class, 'update']);
        Route::post('/candidates/{candidate}/reset-password', [CandidateController::class, 'resetPassword']);
        Route::get('/products', [B2bOrderController::class, 'products']);
        Route::post('/orders/preview', [B2bOrderController::class, 'preview']);
        Route::get('/orders', [B2bOrderController::class, 'index']);
        Route::post('/orders', [B2bOrderController::class, 'store']);
        Route::post('/orders/{batch}/payment', [B2bOrderController::class, 'pay']);
        Route::post('/orders/{batch}/cancel', [B2bOrderController::class, 'cancel']);
        Route::get('/analysis', [CandidateAnalysisController::class, 'index']);
        Route::get('/analysis/{candidate}/ai', [CandidateAnalysisController::class, 'showAi']);
        Route::middleware('throttle:10,1')->post('/analysis/{candidate}/ai', [CandidateAnalysisController::class, 'runAi']);
    });
});
