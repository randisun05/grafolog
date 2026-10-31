<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temuan dari KOMBINASI beberapa Indikator/Aspek/Sindrom sekaligus -
     * beda dari indikator_rules yang sekedar "kalau A tercentang, B juga
     * ikut tercentang" (perluasan bukti yang SAMA). Di sini kombinasi
     * syarat menghasilkan `teks_interpretasi` BARU - satu sifat/pola yang
     * tidak muncul dari salah satu syarat sendirian. Lihat CLAUDE.md
     * "Kombinasi Temuan" untuk alasan kenapa ini entitas terpisah, bukan
     * perluasan indikator_rules.
     *
     * `teks_interpretasi` HARUS diisi dari referensi profesional grafolog
     * (Excel asli, belum didigitalisasi saat migrasi ini ditulis) - bukan
     * dikarang AI, sama prinsipnya dengan seluruh KB lain di sistem ini.
     * Manajemennya (tabel ini + admin UI) dibangun duluan supaya begitu
     * datanya siap tinggal diinput, bukan nunggu Excel dulu baru mulai.
     */
    public function up(): void
    {
        Schema::create('kombinasi_temuan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('teks_interpretasi');
            $table->enum('logika_gabung', ['AND', 'OR'])->default('OR');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kombinasi_temuan');
    }
};
