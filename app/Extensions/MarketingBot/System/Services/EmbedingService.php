<?php

namespace App\Extensions\MarketingBot\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\MarketingBot\System\Embedders\Contracts\EmbedderInterface;
use App\Extensions\MarketingBot\System\Embedders\OpenAIEmbedder;
use App\Extensions\MarketingBot\System\Models\MarketingCampaign;
use App\Extensions\MarketingBot\System\ValueObjects\Embedding;
use App\Extensions\MarketingBot\System\ValueObjects\EmbeddingMap;
use Exception;
use Illuminate\Validation\ValidationException;

class EmbedingService
{
    public EntityEnum $entity;

    public MarketingCampaign $marketingCampaign;

    public function generateEmbedding(string $text): Embedding
    {
        $chunks = $this->splitIntoChunks($text);
        $tokens = 0;
        $maps = [];

        // Split chunks array into groups of 10
        $groups = array_chunk($chunks, 1000);

        foreach ($groups as $index => $group) {
            $group = array_values(array_filter($group));

            if (! $group) {
                continue;
            }

            try {
                $resp = $this->generator()
                    ->setUser($this->marketingCampaign->user)
                    ->setInput($group)
                    ->generate();
            } catch (Exception $th) {
                throw ValidationException::withMessages([
                    'message' => $th->getMessage(),
                ]);
            }

            $json = json_decode(json_encode($resp), false);

            $embeddings = $json->embeddings;

            if (count($embeddings) === 0) {
                continue;
            }

            foreach ($embeddings as $embedding) {
                $maps[] = new EmbeddingMap(
                    $group[$embedding->index],
                    $embedding->embedding
                );
            }
        }

        return new Embedding(...$maps);
    }

    public function generator(): EmbedderInterface
    {
        return app(OpenAIEmbedder::class)
            ->setEntity($this->getEntity());
    }

    private function splitIntoChunks($text, $maxTokens = 1024): array
    {
        $words = explode(' ', $text);
        $chunks = [];
        $chunk = '';

        foreach ($words as $word) {
            if (strlen($word) > $maxTokens) {
                // If the current chunk is not empty, add it to chunks
                if ($chunk !== '') {
                    $chunks[] = trim($chunk);
                    $chunk = '';
                }
                // Split the long word into smaller parts and add them directly
                $wordParts = mb_str_split($word, $maxTokens);
                foreach ($wordParts as $part) {
                    $chunks[] = $part;
                }
            } elseif (strlen($chunk . ' ' . $word) > $maxTokens) {
                $chunks[] = trim($chunk);
                $chunk = $word;
            } else {
                $chunk .= ($chunk === '' ? '' : ' ') . $word;
            }
        }

        if ($chunk !== '') {
            $chunks[] = trim($chunk);
        }

        return $chunks;
    }

    public function getEntity(): EntityEnum
    {
        return $this->entity;
    }

    public function setEntity(EntityEnum $entity): EmbedingService
    {
        $this->entity = $entity;

        return $this;
    }

    public function getMarketingCampaign(): MarketingCampaign
    {
        return $this->marketingCampaign;
    }

    public function setMarketingCampaign(MarketingCampaign $marketingCampaign): static
    {
        $this->marketingCampaign = $marketingCampaign;

        return $this;
    }
}
