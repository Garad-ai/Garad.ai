<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Models;

use Illuminate\Database\Eloquent\Model;

class AIChatProAttachment extends Model
{
    protected $table = 'ai_chat_pro_attachments';

    protected $fillable = [
        'uuid',
        'user_id',
        'original_name',
        'mime_type',
        'file_size',
        'storage_disk',
        'storage_path',
        'checksum',
        'upload_status',
        'processing_status',
        'extraction_status',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];
}
