<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operator/referensi yang menghubungkan 1 Indikator ke Measurement
     * Variable, sesuai rencana KM §3.2. 2 tipe:
     * - 'category': variable_a_id + category_label (mis. "Middle zone
     *   height" @ "large").
     * - 'comparison': variable_a_id [operator] (koefisien x variable_b_id
     *   ATAU compare_value angka tetap) - mis. "d stem width greater_than
     *   1.5x Middle zone height".
     * Desain flat (bukan pohon logika bersarang) - kalau 1 Indikator
     * punya >1 baris, `indikator.rule_group_logic` yang menentukan AND/OR.
     */
    public function up(): void
    {
        Schema::create('indikator_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_id')->constrained('indikator')->cascadeOnDelete();
            $table->enum('rule_type', ['category', 'comparison']);
            $table->foreignId('variable_a_id')->constrained('measurement_variable')->cascadeOnDelete();
            $table->string('category_label', 50)->nullable();
            $table->enum('operator', ['equals', 'greater_than', 'less_than', 'greater_or_equal', 'less_or_equal'])->nullable();
            $table->decimal('koefisien', 8, 4)->default(1.0);
            $table->foreignId('variable_b_id')->nullable()->constrained('measurement_variable')->cascadeOnDelete();
            $table->decimal('compare_value', 10, 4)->nullable();
            $table->timestamps();

            $table->index('indikator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indikator_rules');
    }
};
