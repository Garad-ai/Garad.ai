<?php

namespace App\Extensions\MarketingBot\System\Http\Requests\Campaign;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\MarketingBot\System\Enums\CampaignStatus;
use App\Extensions\MarketingBot\System\Enums\CampaignType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CampaignWhatsappRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id'                => 'required|integer|exists:users,id',
            'name'                   => 'required|string|max:255',
            'content'                => 'sometimes|nullable|string',
            'contacts'               => 'required|array',
            'contacts.*'             => 'integer',
            'segments'               => 'sometimes|nullable|array',
            'segments.*'             => 'sometimes|nullable|integer',
            'scheduled_at'           => 'nullable|date',
            'status'                 => 'string',
            'type'                   => 'required',
            'image'                  => 'sometimes|nullable',
            'ai_embedding_model'     => 'sometimes',
            'ai_reply'               => 'boolean',
            'instruction'            => 'sometimes|nullable|string',
            'meta_template_name'     => 'sometimes|nullable|string',
            'meta_template_language' => 'sometimes|nullable|string',
            'meta_body_variables'    => 'sometimes|nullable|array',
            'meta_body_variables.*'  => 'nullable|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ai_embedding_model' => EntityEnum::TEXT_EMBEDDING_3_SMALL->value,
            'type'               => CampaignType::whatsapp->value,
            'user_id'            => Auth::id(),
            'status'             => request('is_scheduled') ? CampaignStatus::scheduled->value : CampaignStatus::scheduled->value,
            'scheduled_at'       => request('is_scheduled') ? request('scheduled_at') : now(),
            'ai_reply'           => $this->has('ai_reply'),
        ]);
    }
}
