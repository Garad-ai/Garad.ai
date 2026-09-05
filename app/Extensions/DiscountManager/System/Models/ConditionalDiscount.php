<?php

namespace App\Extensions\DiscountManager\System\Models;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ConditionalDiscount extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'coupon_id', 'condition', 'type', 'amount', 'duration', 'total_usage_limit', 'show_strikethrough_price', 'hide_discount_for_subscribed_users', 'user_type', 'payment_gateway', 'pricing_plans', 'allow_once_per_user', 'active', 'scheduled', 'start_date', 'end_date'];

    public function discount(): MorphOne
    {
        return $this->morphOne(Discount::class, 'discountable');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
