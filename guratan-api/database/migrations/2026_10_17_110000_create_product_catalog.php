<?php

use Database\Seeders\ProductSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase B (2026-10-17): katalog produk.
 * - products + product_components: tiap produk punya kegunaan, harga, dan komponen; tiap komponen
 *   punya template laporan (report_fields) - grafologi memakai PersonalityReport yang sudah ada.
 * - orders: pembelian satu produk oleh klien/kandidat. Komponen grafologi membuat handwriting_sample,
 *   komponen lain membuat service_deliveries (dijadwalkan, dikerjakan, dilaporkan).
 * - Harga produk tetap di pricing_plans (riwayat terjaga) dengan kode produk di kolom `tier`, jadi
 *   kolom itu dilonggarkan dari enum ke string. payments.order_id baru; sample_id kini nullable
 *   (produk tanpa tulisan tangan, mis. konseling). discount_codes.is_public/description untuk promo.
 * Driver-aware (raw ALTER untuk MySQL, ->change() untuk sqlite) seperti migrasi enum sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama');
            $table->string('kategori', 20)->default('pendukung'); // utama | pendukung
            $table->string('ringkasan', 255)->nullable();          // value proposition singkat
            $table->text('deskripsi')->nullable();                 // kegunaan lengkap
            $table->string('target', 10)->default('semua');        // b2c | b2b | semua
            $table->boolean('requires_handwriting')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('promo_label', 60)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('tipe', 20); // grafologi | wawancara | konsultasi | konseling | intervensi | lainnya
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->json('report_fields')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('sample_id')->nullable()->constrained('handwriting_samples')->nullOnDelete();
            $table->string('status', 30)->default('menunggu_pembayaran');
            $table->unsignedInteger('base_amount');
            $table->unsignedInteger('amount');
            $table->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->default('website'); // website | grafolog | b2b | admin
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('service_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_component_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('menunggu_penugasan'); // menunggu_penugasan | ditugaskan | dijadwalkan | selesai | dibatalkan
            $table->timestamp('scheduled_at')->nullable();
            $table->string('meeting_url')->nullable();
            $table->json('report')->nullable();
            $table->timestamp('report_submitted_at')->nullable();
            $table->timestamps();
            $table->index(['assigned_to', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('sample_id')->constrained()->nullOnDelete();
        });

        Schema::table('discount_codes', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_active');
            $table->string('description', 255)->nullable()->after('is_public');
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('pricing_plans', fn (Blueprint $t) => $t->string('tier', 50)->change());
            Schema::table('payments', fn (Blueprint $t) => $t->foreignId('sample_id')->nullable()->change());
        } else {
            DB::statement('ALTER TABLE pricing_plans MODIFY COLUMN tier VARCHAR(50) NOT NULL');
            DB::statement('ALTER TABLE payments MODIFY COLUMN sample_id BIGINT UNSIGNED NULL');
        }

        // Comprehensive dihapus dari penjualan (Master jadi produk utama). Data lama tetap terbaca.
        DB::table('pricing_plans')->where('tier', 'comprehensive')->update(['is_active' => false]);

        (new ProductSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('discount_codes', fn (Blueprint $t) => $t->dropColumn(['is_public', 'description']));
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
        });
        Schema::dropIfExists('service_deliveries');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_components');
        Schema::dropIfExists('products');
    }
};
