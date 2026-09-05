<?php

declare(strict_types=1);

/**
 * Static voice + language tables for UGC Factory.
 *
 * Mirrors the OpenAI subset of resources/views/default/panel/user/openai/components/generator_voiceover.blade.php.
 * ElevenLabs voices are fetched live from the API and merged in at runtime.
 */
return [
    'openai_languages' => [
        ['value' => 'english', 'label' => 'English'],
        ['value' => 'spanish', 'label' => 'Spanish'],
        ['value' => 'french', 'label' => 'French'],
        ['value' => 'german', 'label' => 'German'],
        ['value' => 'italian', 'label' => 'Italian'],
        ['value' => 'portuguese', 'label' => 'Portuguese'],
        ['value' => 'dutch', 'label' => 'Dutch'],
        ['value' => 'polish', 'label' => 'Polish'],
        ['value' => 'turkish', 'label' => 'Turkish'],
        ['value' => 'russian', 'label' => 'Russian'],
        ['value' => 'arabic', 'label' => 'Arabic'],
        ['value' => 'chinese', 'label' => 'Chinese'],
        ['value' => 'japanese', 'label' => 'Japanese'],
        ['value' => 'korean', 'label' => 'Korean'],
        ['value' => 'hindi', 'label' => 'Hindi'],
    ],

    'openai_voices' => [
        ['value' => 'alloy', 'label' => 'Alloy'],
        ['value' => 'echo', 'label' => 'Echo'],
        ['value' => 'fable', 'label' => 'Fable'],
        ['value' => 'onyx', 'label' => 'Onyx'],
        ['value' => 'nova', 'label' => 'Nova'],
        ['value' => 'shimmer', 'label' => 'Shimmer'],
    ],
];
