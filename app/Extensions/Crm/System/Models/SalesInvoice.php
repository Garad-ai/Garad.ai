<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SalesInvoice extends Model
{
    protected $table = 'sales_invoices';

    protected $fillable = [
        'user_id',
        'crm_contact_id',
        'crm_company_id',
        'crm_deal_id',
        'invoice_number',
        'status',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'currency',
        'discount_type',
        'discount_value',
        'amount_paid',
        'notes',
        'from_name',
        'from_email',
        'from_phone',
        'from_address',
    ];

    protected $casts = [
        'issue_date'     => 'date',
        'due_date'       => 'date',
        'subtotal'       => 'decimal:2',
        'tax_rate'       => 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'total'          => 'decimal:2',
        'discount_value' => 'decimal:2',
        'amount_paid'    => 'decimal:2',
    ];

    public static function generateNumber(int $userId): string
    {
        $lastInvoice = static::where('user_id', $userId)
            ->orderByRaw('CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;

        if ($lastInvoice) {
            $nextNumber = (int) substr($lastInvoice->invoice_number, 4) + 1;
        }

        return 'INV-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CrmCompany::class, 'crm_company_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesPayment::class);
    }

    public function getBalanceDueAttribute(): float
    {
        return (float) $this->total - (float) $this->amount_paid;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'paid'
            && $this->status !== 'cancelled'
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum(DB::raw('quantity * unit_price'));

        $discountAmount = $this->discount_type === 'percentage'
            ? $subtotal * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        $afterDiscount = max(0, $subtotal - $discountAmount);
        $taxAmount = $afterDiscount * ((float) $this->tax_rate / 100);
        $total = $afterDiscount + $taxAmount;
        $amountPaid = (float) $this->payments()->where('status', 'completed')->sum('amount');

        $this->update([
            'subtotal'    => round($subtotal, 2),
            'tax_amount'  => round($taxAmount, 2),
            'total'       => round($total, 2),
            'amount_paid' => round($amountPaid, 2),
        ]);
    }
}
