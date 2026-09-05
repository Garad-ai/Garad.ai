<?php

namespace App\Extensions\MegaMenu\System\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MegaMenu extends Model
{
    protected $table = 'ext_mega_menus';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'icon',
        'number_of_columns',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MegaMenuItem::class, 'mega_menu_id')
            ->orderBy('order')
            ->with('children')
            ->whereNull('parent_id');
    }

    public function activeItems(): HasMany
    {
        return $this->hasMany(MegaMenuItem::class, 'mega_menu_id')
            ->where('is_active', 1)
            ->orderBy('order')
            ->with('activeChildren')
            ->whereNull('parent_id');
    }
}
