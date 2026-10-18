<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `visible_to` berubah makna menjadi `applies_to` (CV siapa yang memuat variabel ini:
     * all | client | grafolog). `is_hidden` = tidak tampil ke pihak lain (hanya pemilik & admin).
     */
    public function up(): void
    {
        Schema::table('cv_fields', function (Blueprint $table) {
            $table->renameColumn('visible_to', 'applies_to');
            $table->boolean('is_hidden')->default(false)->after('required');
        });
    }

    public function down(): void
    {
        Schema::table('cv_fields', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
            $table->renameColumn('applies_to', 'visible_to');
        });
    }
};
