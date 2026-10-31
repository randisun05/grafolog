<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        // max:2000 - cegah pesan raksasa membengkakkan biaya AI per panggilan.
        return ['message' => ['required', 'string', 'max:2000']];
    }
}
