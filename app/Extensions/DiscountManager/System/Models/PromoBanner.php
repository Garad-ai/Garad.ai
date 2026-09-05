<?php

namespace App\Extensions\DiscountManager\System\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PromoBanner extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'active', 'icon', 'link', 'text_color', 'background_color', 'enable_countdown', 'end_date'];

    public function discount(): MorphOne
    {
        return $this->morphOne(Discount::class, 'discountable');
    }
}
