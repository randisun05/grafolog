<?php

use Database\Seeders\ContentSeeder;
use Database\Seeders\SelfCheckSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase E hasil uji manual (2026-10-17):
 * - Rapid dihidupkan lagi sebagai "cek tulisan terpandu" (lead magnet): pengguna menjawab
 *   pertanyaan tentang ciri tulisannya sendiri; tiap opsi dipetakan ke Indikator KB nyata
 *   (bukan computer vision, bukan skor acak).
 * - SOP refund: permintaan refund klien + keputusan admin.
 * - Produk afiliasi eksternal (tautan mitra + pencatatan klik).
 * - Artikel informasi (mini game kuis memakai data KB langsung, tanpa tabel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_check_questions', function (Blueprint $table) {
            $table->id();
            $table->string('pertanyaan');
            $table->text('petunjuk')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('self_check_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('self_check_questions')->cascadeOnDelete();
            $table->string('label');
            $table->string('ilustrasi', 40)->nullable(); // token gaya contoh tulisan di frontend
            $table->foreignId('indikator_id')->nullable()->constrained('indikator')->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('self_check_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->json('answers');
            $table->json('aspek_ids');
            $table->timestamps();
            $table->index('email');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('refund_requested_at')->nullable()->after('cancel_reason');
            $table->text('refund_reason')->nullable()->after('refund_requested_at');
            $table->string('refund_status', 20)->nullable()->after('refund_reason'); // diajukan | disetujui | ditolak
            $table->text('refund_note')->nullable()->after('refund_status');
            $table->foreignId('refund_decided_by')->nullable()->after('refund_note')->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable()->after('refund_decided_by');
        });

        Schema::create('affiliate_products', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('mitra');
            $table->string('ringkasan', 500)->nullable();
            $table->string('url');
            $table->string('kategori', 50)->default('lainnya');
            $table->string('target', 10)->default('semua'); // semua | b2c | b2b
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->string('slug')->unique();
            $table->string('ringkasan', 500)->nullable();
            $table->longText('isi');
            $table->string('kategori', 50)->default('umum');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        (new SelfCheckSeeder)->run();
        (new ContentSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_products');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_decided_by');
            $table->dropColumn(['refund_requested_at', 'refund_reason', 'refund_status', 'refund_note', 'refunded_at']);
        });
        Schema::dropIfExists('self_check_results');
        Schema::dropIfExists('self_check_options');
        Schema::dropIfExists('self_check_questions');
    }
};
