<?php

namespace App\Extensions\MarketingBot\System\Generators\Contracts;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\MarketingBot\System\Models\MarketingConversation;
use App\Extensions\MarketingBot\System\Models\MarketingMessageHistory;
use App\Extensions\MarketingBot\System\Services\Common\Traits\HasMarketingCampaign;
use Illuminate\Database\Eloquent\Collection;

abstract class Generator implements GeneratorInterface
{
    use HasMarketingCampaign;

    public string $prompt;

    public EntityEnum $entity;

    public MarketingConversation $conversation;

    public function histories(): Collection|array
    {
        return MarketingMessageHistory::query()
            ->where('conversation_id', $this->conversation->id)
            ->select('message', 'role', 'id')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    public function setPrompt(string $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getEntity(): EntityEnum
    {
        return $this->entity;
    }

    public function setEntity(EntityEnum $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getConversation(): MarketingConversation
    {
        return $this->conversation;
    }

    public function setConversation(MarketingConversation $conversation): static
    {
        $this->conversation = $conversation;

        return $this;
    }
}
