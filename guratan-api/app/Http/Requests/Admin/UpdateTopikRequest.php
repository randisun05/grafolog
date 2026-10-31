<?php

namespace App\Http\Requests\Admin;

/**
 * Sama persis dengan StoreTopikRequest - unique-nama-nya sudah menangani
 * pengecualian id sendiri lewat $this->route('topik') di rules().
 */
class UpdateTopikRequest extends StoreTopikRequest {}
