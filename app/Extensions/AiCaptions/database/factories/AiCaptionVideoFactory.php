<?php

declare(strict_types=1);

namespace App\Extensions\AiCaptions\Database\Factories;

use App\Extensions\AiCaptions\System\Models\AiCaptionVideo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiCaptionVideoFactory extends Factory
{
    protected $model = AiCaptionVideo::class;

    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'is_demo'           => false,
            'title'             => $this->faker->sentence(3),
            'template_id'       => 'line-by-line',
            'template_name'     => 'Line by Line',
            'source_url'        => 'https://example.com/source.mp4',
            'source_file_path'  => null,
            'captions_video_id' => null,
            'status'            => 'processing',
            'error_message'     => null,
            'output_url'        => null,
            'local_path'        => null,
            'duration_seconds'  => 30,
            'usage_deducted'    => false,
            'metadata'          => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'status'         => 'completed',
            'output_url'     => 'https://example.com/captioned.mp4',
            'usage_deducted' => true,
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status'        => 'failed',
            'error_message' => 'Generation failed.',
        ]);
    }
}
