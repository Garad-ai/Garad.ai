<?php

namespace App\Extensions\DiscountManager\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DiscountStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()?->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'                              => 'required|string|max:255',
            'condition'                          => 'required|string',
            'type'                               => 'required|string',
            'amount'                             => 'required|numeric|min:0',
            'duration'                           => 'required|string',
            'total_usage_limit'                  => 'required|integer|min:0',
            'show_strikethrough_price'           => 'boolean',
            'hide_discount_for_subscribed_users' => 'boolean',
            'user_type'                          => 'nullable|array',
            'user_type.*'                        => 'string',
            'payment_gateway'                    => 'nullable|array',
            'pricing_plans'                      => 'nullable|array',
            'allow_once_per_user'                => 'boolean',
            'active'                             => 'boolean',
            'scheduled'                          => 'boolean',
            'start_date'                         => 'required_if:scheduled,1|nullable|date|after_or_equal:now',
            'end_date'                           => 'required_if:scheduled,1|nullable|date|after:start_date',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'show_strikethrough_price'           => $this->has('show_strikethrough_price'),
            'hide_discount_for_subscribed_users' => $this->has('hide_discount_for_subscribed_users'),
            'allow_once_per_user'                => $this->has('allow_once_per_user'),
            'active'                             => $this->has('active'),
            'scheduled'                          => $this->has('scheduled'),
        ]);
    }
}
