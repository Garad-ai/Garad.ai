<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Models;

use App\Models\UserOpenaiChat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIChatProDocument extends Model
{
    use HasFactory;

    protected $table = 'ai_chat_pro_documents';

    protected $fillable = [
        'user_openai_chat_id',
        'user_id',
        'status',
        'original_name',
        'file_name',
        'file_extension',
        'mime_type',
        'file_size',
        'storage_disk',
        'storage_path',
        'reference_url',
        'content_hash',
        'extracted_text',
        'chunks',
        'metadata',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'chunks' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(UserOpenaiChat::class, 'user_openai_chat_id');
    }
}