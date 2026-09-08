<?php

namespace App\Http\Requests\Supervisor;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // max:2000 - cegah pesan raksasa membengkakkan biaya per-call,
            // sama filosofi guard biaya AI yang lain di codebase ini.
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
