<?php

namespace App\Http\Requests\Admin;

/**
 * Sama persis dengan StoreIndikatorRuleRequest - edit 1 aturan selalu
 * kirim bentuk lengkap (tidak ada varian "sometimes" parsial), karena
 * validasi lintas-field rule_type/category_label/operator/dst di
 * withValidator() cuma masuk akal dievaluasi atas payload penuh.
 */
class UpdateIndikatorRuleRequest extends StoreIndikatorRuleRequest {}
