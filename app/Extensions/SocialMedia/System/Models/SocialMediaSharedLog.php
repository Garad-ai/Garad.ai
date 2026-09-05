<?php

namespace App\Extensions\SocialMedia\System\Models;

use App\Extensions\SocialMedia\System\Enums\LogStatusEnum;
use Illuminate\Database\Eloquent\Model;

class SocialMediaSharedLog extends Model
{
    public $timestamps = false;

    protected $table = 'ext_social_media_shared_logs';

    protected $fillable = [
        'social_media_post_id',
        'response',
        'status',
        'created_at',
    ];

    protected $casts = [
        'response'   => 'json',
        'created_at' => 'datetime',
        'status'     => LogStatusEnum::class,
    ];
}
