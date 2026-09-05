<?php

namespace App\Extensions\MarketingBot\System\Http\Requests\Campaign;

use App\Extensions\MarketingBot\System\Enums\CampaignStatus;
use App\Extensions\MarketingBot\System\Enums\CampaignType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CampaignTelegramRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id'      => 'required|integer|exists:users,id',
            'name'         => 'required|string|max:255',
            'content'      => 'required|string',
            'contacts'     => 'required|array',
            'contacts.*'   => 'integer',
            'scheduled_at' => 'nullable|date',
            'status'       => 'string',
            'type'         => 'required',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type'         => CampaignType::telegram->value,
            'user_id'      => Auth::id(),
            'status'       => request('is_scheduled') ? CampaignStatus::scheduled->value : CampaignStatus::scheduled->value,
            'scheduled_at' => request('is_scheduled') ? request('scheduled_at') : now(),
        ]);
    }
}
