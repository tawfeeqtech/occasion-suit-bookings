<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateItemRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may create inventory.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->tenant_id !== null
            && in_array($user->role, ['staff', 'owner'], true);
    }

    /**
     * Prepare defaults for optional item attributes without overriding supplied values.
     */
    protected function prepareForValidation(): void
    {
        $defaults = [
            'category' => 'عام',
            'size' => 'غير محدد',
            'color' => 'غير محدد',
            'rental_price' => 0.00,
            'status' => 'available',
            'custom_fields' => null,
        ];

        $this->merge(array_merge($defaults, $this->only(array_keys($defaults))));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'category' => ['string', 'max:100'],
            'size' => ['string', 'max:50'],
            'color' => ['string', 'max:50'],
            'rental_price' => ['numeric', 'min:0'],
            'status' => ['string', 'in:available,booked,out_with_customer,cleaning,maintenance'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
