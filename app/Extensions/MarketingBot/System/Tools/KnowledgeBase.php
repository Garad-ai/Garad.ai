<?php

namespace App\Extensions\MarketingBot\System\Tools;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\MarketingBot\System\Embedders\Contracts\EmbedderInterface;
use App\Extensions\MarketingBot\System\Embedders\OpenAIEmbedder;
use App\Extensions\MarketingBot\System\Services\Common\Traits\HasMarketingCampaign;
use Illuminate\Support\Collection;
use JsonException;

class KnowledgeBase
{
    use HasMarketingCampaign;

    /**
     * @throws JsonException
     */
    public function call(
        EntityEnum $entityEnum,
        string $query,
        Collection $knowledgeBase,
    ): string {

        $generator = $this
            ->generator($entityEnum)
            ->setInput($query)
            ->generate();

        $queryEmbedding = $generator->embeddings[0]->embedding;

        $results = [];

        foreach ($knowledgeBase as $embedding) {

            if (! $embedding['embedding']) {
                continue;
            }

            foreach ($embedding['embedding'] as $em) {
                $similarity = $this->cosineSimilarity(
                    $em['embedding'],
                    $queryEmbedding
                );

                $results[] = [
                    'content'    => $em['content'],
                    'similarity' => $similarity,
                ];
            }
        }

        usort($results, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        $results = array_slice($results, 0, 5);
        $texts = array_map(function ($r) {
            return $r['content'];
        }, $results);

        return json_encode($texts, JSON_THROW_ON_ERROR);
    }

    public function generator(EntityEnum $entityEnum): EmbedderInterface
    {
        return app(OpenAIEmbedder::class)->setEntity($entityEnum)->setUser($this->marketingCampaign->user);
    }

    private function cosineSimilarity($vec1, $vec2)
    {
        $dot_product = 0.0;
        $vec1_magnitude = 0.0;
        $vec2_magnitude = 0.0;

        $length = count($vec1);

        for ($i = 0; $i < $length; $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $vec1_magnitude += $vec1[$i] * $vec1[$i];
            $vec2_magnitude += $vec2[$i] * $vec2[$i];
        }

        $vec1_magnitude = sqrt($vec1_magnitude);
        $vec2_magnitude = sqrt($vec2_magnitude);

        if ($vec1_magnitude == 0.0 || $vec2_magnitude == 0.0) {
            return 0.0;
        }

        return $dot_product / ($vec1_magnitude * $vec2_magnitude);
    }
}
