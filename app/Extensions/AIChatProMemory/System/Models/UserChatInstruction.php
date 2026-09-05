<?php

namespace App\Extensions\AIChatProMemory\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserChatInstruction extends Model
{
    protected $fillable = [
        'user_id',
        'openai_chat_category_id',
        'ip_address',
        'instructions',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Models\OpenaiGeneratorFilter::class, 'openai_chat_category_id');
    }

    /**
     * Get instruction for authenticated user
     */
    public static function getForUser(int $userId, int $categoryId): ?string
    {
        return static::where('user_id', $userId)
            ->where('openai_chat_category_id', $categoryId)
            ->value('instructions');
    }

    /**
     * Get instruction for guest by IP
     */
    public static function getForGuest(string $ipAddress, int $categoryId): ?string
    {
        return static::whereNull('user_id')
            ->where('ip_address', $ipAddress)
            ->where('openai_chat_category_id', $categoryId)
            ->value('instructions');
    }

    /**
     * Set instruction for user
     */
    public static function setForUser(int $userId, int $categoryId, ?string $instructions): self
    {
        return static::updateOrCreate(
            [
                'user_id'                 => $userId,
                'openai_chat_category_id' => $categoryId,
            ],
            [
                'instructions' => $instructions ?? '',
                'ip_address'   => null, // Clear IP for authenticated users
            ]
        );
    }

    /**
     * Set instruction for guest by IP
     */
    public static function setForGuest(string $ipAddress, int $categoryId, string $instructions): self
    {
        return static::updateOrCreate(
            [
                'user_id'                 => null,
                'ip_address'              => $ipAddress,
                'openai_chat_category_id' => $categoryId,
            ],
            [
                'instructions' => $instructions,
            ]
        );
    }

    /**
     * Clean up old guest instructions (older than 90 days)
     */
    public static function cleanupOldGuest(): int
    {
        return static::whereNull('user_id')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();
    }
}
