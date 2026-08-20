<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Full edit is allowed here (unlike PricingPlan/DiscountCode) - an
     * announcement has no "past usage" whose meaning would be retroactively
     * changed by editing it, so there's no deactivate-and-recreate rule.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:2000'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'in:user,grafolog,hr,administrator,supervisor'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
