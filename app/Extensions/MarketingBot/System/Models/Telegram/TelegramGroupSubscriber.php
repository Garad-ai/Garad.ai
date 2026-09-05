<?php

namespace App\Extensions\MarketingBot\System\Models\Telegram;

use App\Events\ContactCapturedEvent;
use Database\Factories\TelegramGroupSubscriberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramGroupSubscriber extends Model
{
    use HasFactory;

    protected static function newFactory(): TelegramGroupSubscriberFactory
    {
        return TelegramGroupSubscriberFactory::new();
    }

    /**
     * Notify the CRM (and any other listener) whenever a real subscriber is
     * recorded, regardless of which webhook path created it. Bots are skipped.
     * Inert unless the CRM extension is installed and sync is enabled.
     */
    protected static function booted(): void
    {
        static::created(function (self $subscriber): void {
            if ((bool) $subscriber->getAttribute('is_bot')) {
                return;
            }

            ContactCapturedEvent::dispatch(
                (int) $subscriber->getAttribute('user_id'),
                trim((string) $subscriber->getAttribute('name')),
                null,
                $subscriber->getAttribute('phone'),
                null,
                'marketing_telegram',
            );
        });
    }

    protected $table = 'ext_telegram_group_subscribers';

    protected $fillable = [
        'user_id',
        'name',
        'username',
        'avatar',
        'phone',
        'client_id',
        'group_chat_id',
        'group_subscriber_id',
        'group_id',
        'unique_id',
        'is_left_group',
        'type',
        'status',
        'is_blacklist',
        'is_bot',
        'is_admin',
        'scopes',
    ];
}
