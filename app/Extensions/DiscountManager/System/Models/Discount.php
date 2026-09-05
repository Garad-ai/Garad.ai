<?php

namespace App\Extensions\DiscountManager\System\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = ['discountable_id', 'discountable_type'];

    public function discountable(): MorphTo
    {
        return $this->morphTo();
    }

    public function type(): string
    {
        return $this->discountable_type == 'App\Extensions\DiscountManager\System\Models\ConditionalDiscount' ? 'Discount' : 'Promo Banner';
    }
}
