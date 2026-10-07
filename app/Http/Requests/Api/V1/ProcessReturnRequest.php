<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProcessReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.booking_item_id' => ['required', 'uuid'],
            'items.*.return_status' => ['required', 'string', 'in:clean_pass,damaged,missing'],
            'items.*.penalty_fee' => ['required_if:items.*.return_status,damaged,missing', 'nullable', 'numeric', 'min:0'],
            'items.*.penalty_reason' => ['required_if:items.*.return_status,damaged,missing', 'nullable', 'string', 'max:500'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
            'remaining_balance_collected' => ['nullable', 'numeric', 'min:0'],
            'penalty_collected' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,palpay,jawwal_pay,bank_transfer'],
            'release_collateral' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'يجب تحديد حالة جميع عناصر الحجز لإتمام الإرجاع',
            'items.*.penalty_fee.required_if' => 'مبلغ وسبب الغرامة مطلوبان في حال التلف أو الفقدان',
            'items.*.penalty_reason.required_if' => 'مبلغ وسبب الغرامة مطلوبان في حال التلف أو الفقدان',
        ];
    }
}
