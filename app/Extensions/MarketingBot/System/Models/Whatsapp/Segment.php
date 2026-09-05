<?php

namespace App\Extensions\MarketingBot\System\Models\Whatsapp;

use Database\Factories\SegmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Segment extends Model
{
    use HasFactory;

    protected static function newFactory(): SegmentFactory
    {
        return SegmentFactory::new();
    }

    protected $table = 'ext_segments';

    protected $fillable = [
        'user_id',
        'name',
        'status',
    ];

    public function scopeMy(Builder $builder, int $status = 1): void
    {
        $builder
            ->where('user_id', auth()->id())
            ->where('status', $status);
    }
}
