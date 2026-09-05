<?php

namespace App\Extensions\MarketingBot\System\Models\Whatsapp;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    protected $table = 'ext_contacts';

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
