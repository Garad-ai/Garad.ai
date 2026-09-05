<?php

namespace App\Extensions\MegaMenu\System\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MegaMenuItem extends Model
{
    protected $table = 'ext_mega_menu_items';

    protected $fillable = [
        'mega_menu_id',
        'parent_id',
        'label',
        'description',
        'type',
        'icon',
        'link',
        'order',
        'is_active',
        'route',
        'params',
        'space',
    ];

    protected $casts = [
        'params' => 'json',
    ];

    protected $appends = [
        'icon_url',
    ];

    public function iconUrl(): Attribute
    {
        return Attribute::make(get: function () {
            return $this->icon ? Storage::disk('uploads')->url($this->icon) : null;
        });
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('order');
    }

    public function activeChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', 1)
            ->orderBy('order');
    }
}
