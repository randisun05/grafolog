<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menentukan AND/OR untuk gabungan >1 baris `indikator_rules` milik 1
     * Indikator (KM-E). Ditaruh di Indikator, bukan diulang di setiap baris
     * aturan - satu Indikator cuma punya SATU cara gabung aturan, mengulang
     * nilainya di tiap baris cuma berisiko drift (2 baris aturan 1 Indikator
     * yang sama tapi group_logic-nya beda, keadaan yang tidak masuk akal).
     * Default 'OR' sesuai konfirmasi user: varian a/b/c 1 posisi di-OR-kan.
     */
    public function up(): void
    {
        Schema::table('indikator', function (Blueprint $table) {
            $table->enum('rule_group_logic', ['AND', 'OR'])->default('OR')->after('varian');
        });
    }

    public function down(): void
    {
        Schema::table('indikator', function (Blueprint $table) {
            $table->dropColumn('rule_group_logic');
        });
    }
};
