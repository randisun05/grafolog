<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment gateway diganti dari DOKU ke Xendit (2026-09-20, permintaan
 * user langsung) - lihat guratan-api/CLAUDE.md "Payment (Xendit)" untuk
 * detail penuh. Tidak ada data produksi nyata di kolom doku_* yang perlu
 * dipertahankan (DOKU belum pernah benar-benar dikonfigurasi/dites lawan
 * sandbox sungguhan), jadi ini rename bersih (tambah kolom baru + hapus
 * kolom lama), bukan migrasi data. `payments.doku_payment_channel` sudah
 * dead sejak awal (skema-saja, tidak pernah ditulis PaymentController) -
 * tidak dibawa ke skema baru, bukan lupa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('xendit_invoice_id')->nullable()->after('status');
            $table->string('xendit_invoice_url')->nullable()->after('xendit_invoice_id');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['doku_token_id', 'doku_payment_url', 'doku_payment_channel']);
        });

        Schema::table('token_purchases', function (Blueprint $table) {
            $table->string('xendit_invoice_id')->nullable()->after('status');
            $table->string('xendit_invoice_url')->nullable()->after('xendit_invoice_id');
        });
        Schema::table('token_purchases', function (Blueprint $table) {
            $table->dropColumn(['doku_token_id', 'doku_payment_url']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('doku_token_id')->nullable();
            $table->string('doku_payment_url')->nullable();
            $table->string('doku_payment_channel')->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['xendit_invoice_id', 'xendit_invoice_url']);
        });

        Schema::table('token_purchases', function (Blueprint $table) {
            $table->string('doku_token_id')->nullable();
            $table->string('doku_payment_url')->nullable();
        });
        Schema::table('token_purchases', function (Blueprint $table) {
            $table->dropColumn(['xendit_invoice_id', 'xendit_invoice_url']);
        });
    }
};
