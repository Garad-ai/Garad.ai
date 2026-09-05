<?php

namespace App\Extensions\AIImagePro\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiImageProLikeModel extends Model
{
    use HasFactory;

    protected $table = 'ai_image_pro_likes';

    protected $fillable = [
        'ai_image_pro_id',
        'user_id',
        'guest_ip',
    ];

    /**
     * Get the image that was liked.
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(AiImageProModel::class, 'ai_image_pro_id');
    }

    /**
     * Get the user who liked the image.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
