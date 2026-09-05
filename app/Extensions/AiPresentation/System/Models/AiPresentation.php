<?php

namespace App\Extensions\AiPresentation\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPresentation extends Model
{
    protected $fillable = [
        'user_id',
        'generation_id',
        'status',
        'format',
        'theme_name',
        'num_cards',
        'input_text',
        'request_data',
        'response_data',
        'gamma_url',
        'pdf_url',
        'pptx_url',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'request_data'  => 'array',
        'response_data' => 'array',
        'completed_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTotalPagesAttribute(): int
    {
        $pdf = public_path($this->pdf_url);
        if (! file_exists($pdf)) {
            return 0;
        }
        $content = file_get_contents($pdf);
        $matches = [];
        preg_match_all('/\/Count\s+(\d+)/', $content, $matches);

        return ! empty($matches[1]) ? (int) max($matches[1]) : 0;
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
