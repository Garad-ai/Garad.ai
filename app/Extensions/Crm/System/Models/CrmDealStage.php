<?php

namespace App\Extensions\Crm\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmDealStage extends Model
{
    protected $table = 'crm_deal_stages';

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }

    /**
     * Create default stages for a user if they don't exist yet.
     */
    public static function createDefaultsForUser(int $userId): void
    {
        if (self::where('user_id', $userId)->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'Lead', 'color' => '#6366f1', 'order' => 0],
            ['name' => 'Qualified', 'color' => '#8b5cf6', 'order' => 1],
            ['name' => 'Proposal', 'color' => '#f59e0b', 'order' => 2],
            ['name' => 'Negotiation', 'color' => '#3b82f6', 'order' => 3],
            ['name' => 'Won', 'color' => '#22c55e', 'order' => 4],
            ['name' => 'Lost', 'color' => '#ef4444', 'order' => 5],
        ];

        foreach ($defaults as $stage) {
            self::create(array_merge($stage, ['user_id' => $userId]));
        }
    }
}
