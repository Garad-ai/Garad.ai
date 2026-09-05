<?php

namespace App\Extensions\Crm\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmPresentationSlide extends Model
{
    protected $table = 'crm_presentation_slides';

    protected $fillable = [
        'crm_presentation_id',
        'sort_order',
        'slide_type',
        'title',
        'content',
        'image_prompt',
        'fal_request_id',
        'status',
        'image_url',
        'error_message',
    ];

    protected $casts = [
        'content'    => 'array',
        'sort_order' => 'integer',
    ];

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(CrmPresentation::class, 'crm_presentation_id');
    }
}
