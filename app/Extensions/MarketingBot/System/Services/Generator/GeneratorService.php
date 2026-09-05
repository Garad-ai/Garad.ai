<?php

namespace App\Extensions\MarketingBot\System\Services\Generator;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\MarketingBot\System\Generators\OpenAIGenerator;
use App\Extensions\MarketingBot\System\Models\MarketingConversation;
use App\Extensions\MarketingBot\System\Services\Common\Traits\HasMarketingCampaign;
use App\Models\Setting;

class GeneratorService
{
    use HasMarketingCampaign;

    public string $prompt;

    public MarketingConversation $conversation;

    public EntityEnum $entityEnum;

    public function generate(): string
    {
        $generator = $this->generator();

        $driver = Entity::driver($this->entityEnum)
            ->forUser($this->marketingCampaign->user);

        if (! $driver->hasCreditBalance()) {
            return trans('You have no credits left. Please consider upgrading your plan.');
        }

        $generated = $generator
            ->setConversation($this->conversation)
            ->setMarketingCampaign($this->marketingCampaign)
            ->setEntity($this->entityEnum)
            ->setPrompt($this->prompt)
            ->generate();

        $driver
            ->input($generated)
            ->calculateCredit()
            ->decreaseCredit();

        return $generated;
    }

    public function generator(): OpenAIGenerator
    {
        $setting = Setting::getCache();

        // TODO: chatbot model default openai_default_model
        //		$model = $this->chatbot->getAttribute('ai_model');
        $model = $setting->openai_default_model;

        $this->entityEnum = EntityEnum::fromSlug($setting->openai_default_model);

        $engine = $this->entityEnum->engine();

        return app(OpenAIGenerator::class);
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): static
    {
        $this->prompt = $prompt;

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
