<?php

namespace App\Extensions\MarketingBot\System\Services;

use App\Extensions\MarketingBot\System\Models\MarketingBotMonthlyUsage;
use App\Extensions\MarketingBot\System\Models\Whatsapp\ContactList;
use App\Models\User;
use Illuminate\Support\Carbon;

class MarketingBotLimitService
{
    public const UNLIMITED = -1;

    public function __construct(private readonly User $user) {}

    /**
     * Get a numeric limit from the plan. Returns -1 if no plan or key missing (unlimited).
     */
    public function getLimit(string $key): int
    {
        $limits = (array) ($this->user->relationPlan?->marketing_bot_limits ?? []);
        $value = $limits[$key] ?? self::UNLIMITED;

        return is_numeric($value) ? (int) $value : self::UNLIMITED;
    }

    /**
     * Get the list of enabled channels from the plan.
     * Returns all channels when not configured (unlimited).
     */
    public function getAllowedChannels(): array
    {
        $limits = (array) ($this->user->relationPlan?->marketing_bot_limits ?? []);
        $channels = $limits['channels'] ?? null;

        return is_array($channels) ? $channels : ['whatsapp', 'telegram'];
    }

    /**
     * Check if the user can add more contacts.
     */
    public function canAddContact(): bool
    {
        $limit = $this->getLimit('max_contacts');

        if ($limit === self::UNLIMITED) {
            return true;
        }

        $current = ContactList::query()
            ->where('user_id', $this->user->getKey())
            ->count();

        return $current < $limit;
    }

    /**
     * Check if the user can send more messages this month.
     */
    public function canSendMessage(): bool
    {
        $limit = $this->getLimit('monthly_messages');

        if ($limit === self::UNLIMITED) {
            return true;
        }

        return $this->getMonthlyMessageCount() < $limit;
    }

    /**
     * Check if the user has access to a specific channel.
     */
    public function canAccessChannel(string $channel): bool
    {
        return in_array($channel, $this->getAllowedChannels(), true);
    }

    /**
     * Increment the monthly message counter for the user.
     */
    public function incrementMonthlyMessages(): void
    {
        $now = Carbon::now();

        MarketingBotMonthlyUsage::query()->updateOrCreate(
            [
                'user_id' => $this->user->getKey(),
                'year'    => $now->year,
                'month'   => $now->month,
            ],
            ['messages_sent' => 0]
        );

        MarketingBotMonthlyUsage::query()
            ->where('user_id', $this->user->getKey())
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->increment('messages_sent');
    }

    /**
     * Get the number of messages sent by the user this month.
     */
    public function getMonthlyMessageCount(): int
    {
        $now = Carbon::now();

        return (int) MarketingBotMonthlyUsage::query()
            ->where('user_id', $this->user->getKey())
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->value('messages_sent');
    }

    /**
     * Get remaining contact slots. Returns null for unlimited.
     */
    public function remainingContacts(): ?int
    {
        $limit = $this->getLimit('max_contacts');

        if ($limit === self::UNLIMITED) {
            return null;
        }

        $current = ContactList::query()
            ->where('user_id', $this->user->getKey())
            ->count();

        return max(0, $limit - $current);
    }

    /**
     * Get remaining messages for this month. Returns null for unlimited.
     */
    public function remainingMessages(): ?int
    {
        $limit = $this->getLimit('monthly_messages');

        if ($limit === self::UNLIMITED) {
            return null;
        }

        return max(0, $limit - $this->getMonthlyMessageCount());
    }
}
