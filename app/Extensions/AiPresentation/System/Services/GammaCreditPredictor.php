<?php

namespace App\Extensions\AiPresentation\System\Services;

class GammaCreditPredictor
{
    // Base cost per card
    protected const CREDITS_PER_CARD = 3;

    // Image model costs
    protected const IMAGE_MODEL_COSTS = [
        // Basic models (2 credits/image)
        'flux-1-quick'        => 2,
        'flux-kontext-fast'   => 2,
        'imagen-3-flash'      => 2,
        'luma-photon-flash-1' => 2,

        // Advanced models (8-15 credits/image)
        'flux-1-pro'        => 8,
        'imagen-3-pro'      => 8,
        'ideogram-v3-turbo' => 10,
        'luma-photon-1'     => 10,
        'leonardo-phoenix'  => 15,

        // Premium models (20-33 credits/image)
        'flux-kontext-pro'   => 20,
        'ideogram-v3'        => 20,
        'imagen-4-pro'       => 20,
        'recraft-v3'         => 20,
        'gpt-image-1-medium' => 30,
        'flux-1-ultra'       => 30,
        'imagen-4-ultra'     => 30,
        'dall-e-3'           => 33,

        // Ultra models (40-120 credits/image)
        'flux-kontext-max'    => 40,
        'recraft-v3-svg'      => 40,
        'ideogram-v3-quality' => 45,
        'gpt-image-1-high'    => 120,
    ];

    // Default image model costs by category (for when model is not specified)
    protected const DEFAULT_MODEL_COST = 10; // Middle-range estimate

    /**
     * Predict credit cost for a presentation generation request
     *
     * @param  array  $requestData  The validated request data
     *
     * @return array ['min' => int, 'max' => int, 'estimated' => int]
     */
    public function predictCredits(array $requestData): array
    {
        $numCards = (int) ($requestData['presentation_count'] ?? $requestData['numCards'] ?? 10);

        // Base cost for cards
        $cardsCost = $numCards * self::CREDITS_PER_CARD;

        // Calculate image costs
        $imageCosts = $this->calculateImageCosts($requestData, $numCards);

        return [
            'min'       => $cardsCost + $imageCosts['min'],
            'max'       => $cardsCost + $imageCosts['max'],
            'estimated' => $cardsCost + $imageCosts['estimated'],
            'breakdown' => [
                'cards' => [
                    'count' => $numCards,
                    'cost'  => $cardsCost,
                ],
                'images' => $imageCosts,
            ],
        ];
    }

    /**
     * Calculate image-related costs
     *
     * @return array ['min' => int, 'max' => int, 'estimated' => int, 'per_image' => int, 'estimated_count' => int]
     */
    protected function calculateImageCosts(array $requestData, int $numCards): array
    {
        $imageSource = $requestData['imageOptions']['source'] ?? null;

        // If images are not AI generated, return zero cost
        if ($imageSource !== 'aiGenerated') {
            return [
                'min'             => 0,
                'max'             => 0,
                'estimated'       => 0,
                'per_image'       => 0,
                'estimated_count' => 0,
            ];
        }

        // Get the specified model or use default
        $imageModel = $requestData['imageOptions']['model'] ?? null;
        $costPerImage = $this->getImageModelCost($imageModel);

        // Estimate number of images based on card count
        // Typical ratio: ~50-70% of cards have images
        $estimatedImageCount = (int) ceil($numCards * 0.6);

        // Calculate costs
        $minImageCount = (int) ceil($numCards * 0.3); // Conservative estimate (30% of cards)
        $maxImageCount = $numCards; // Maximum (1 image per card)

        return [
            'min'             => $minImageCount * $this->getMinCostForModel($imageModel),
            'max'             => $maxImageCount * $this->getMaxCostForModel($imageModel),
            'estimated'       => $estimatedImageCount * $costPerImage,
            'per_image'       => $costPerImage,
            'estimated_count' => $estimatedImageCount,
            'model'           => $imageModel ?? 'auto-selected',
        ];
    }

    /**
     * Get the cost for a specific image model
     */
    protected function getImageModelCost(?string $model): int
    {
        if (! $model) {
            return self::DEFAULT_MODEL_COST;
        }

        return self::IMAGE_MODEL_COSTS[$model] ?? self::DEFAULT_MODEL_COST;
    }

    /**
     * Get minimum cost for a model category
     */
    protected function getMinCostForModel(?string $model): int
    {
        if (! $model) {
            return 2; // Minimum basic model cost
        }

        $cost = $this->getImageModelCost($model);

        // Return the actual cost for known models
        // For unknown models, return minimum of category
        if (isset(self::IMAGE_MODEL_COSTS[$model])) {
            return $cost;
        }

        return 2; // Default minimum
    }

    /**
     * Get maximum cost for a model category
     */
    protected function getMaxCostForModel(?string $model): int
    {
        if (! $model) {
            return 20; // Reasonable maximum for auto-selected models
        }

        $cost = $this->getImageModelCost($model);

        // Return the actual cost for known models
        if (isset(self::IMAGE_MODEL_COSTS[$model])) {
            return $cost;
        }

        return 20; // Default maximum
    }

    /**
     * Check if user has sufficient credits
     *
     * @return array ['sufficient' => bool, 'prediction' => array, 'shortfall' => float]
     */
    public function checkSufficientCredits(float $userCredits, array $requestData): array
    {
        $prediction = $this->predictCredits($requestData);
        $estimatedCost = $prediction['estimated'];

        return [
            'sufficient'   => $userCredits >= $estimatedCost,
            'prediction'   => $prediction,
            'shortfall'    => max(0, $estimatedCost - $userCredits),
            'user_credits' => $userCredits,
        ];
    }

    /**
     * Get a human-readable credit estimate message
     */
    public function getEstimateMessage(array $requestData): string
    {
        $prediction = $this->predictCredits($requestData);

        $message = sprintf(
            'Estimated cost: %d credits (range: %d-%d credits)',
            $prediction['estimated'],
            $prediction['min'],
            $prediction['max']
        );

        $breakdown = $prediction['breakdown'];
        $message .= sprintf(
            ' | Cards: %d × %d = %d credits',
            $breakdown['cards']['count'],
            self::CREDITS_PER_CARD,
            $breakdown['cards']['cost']
        );

        if ($breakdown['images']['estimated'] > 0) {
            $message .= sprintf(
                ' | Images: ~%d × %d = %d credits',
                $breakdown['images']['estimated_count'],
                $breakdown['images']['per_image'],
                $breakdown['images']['estimated']
            );
        }

        return $message;
    }

    /**
     * Get model category for display purposes
     */
    public function getModelCategory(?string $model): string
    {
        if (! $model) {
            return 'Auto-selected';
        }

        $cost = $this->getImageModelCost($model);

        if ($cost <= 2) {
            return 'Basic';
        }

        if ($cost <= 15) {
            return 'Advanced';
        }

        if ($cost <= 40) {
            return 'Premium';
        }

        return 'Ultra';
    }
}
