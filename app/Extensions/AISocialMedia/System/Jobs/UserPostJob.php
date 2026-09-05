<?php

namespace App\Extensions\AISocialMedia\System\Jobs;

use App\Domains\Entity\Facades\Entity;
use App\Extensions\AISocialMedia\System\Models\ScheduledPost;
use App\Extensions\AISocialMedia\System\Services\AutomationService;
use App\Extensions\AISocialMedia\System\Services\Contracts\BaseService;
use App\Helpers\Classes\Helper;
use App\Models\Usage;
use App\Services\Ai\AiCompletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class UserPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected ScheduledPost $post;

    public function __construct(ScheduledPost $post)
    {
        $this->post = $post;
    }

    public function handle(): void
    {
        $this->getService($this->post)
            ->share(
                $this->content($this->post)
            );
    }

    public function content(ScheduledPost $post): ?string
    {
        if ($post->auto_generate) {
            $content = app(AiCompletionService::class)->completeUserOnly($this->post->prompt);

            $driver = Entity::driver(Helper::defaultWordModel())
                ->forUser($this->post->user);

            $driver->input($content)->calculateCredit();

            $driver->redirectIfNoCreditBalance();

            $driver->decreaseCredit();
            Usage::getSingle()->updateWordCounts($driver->calculate());

            return $content;
        }

        return $post->getAttribute('content');
    }

    public function getService(ScheduledPost $post): BaseService
    {
        $platform = AutomationService::find($post->platform);

        $service = $platform?->service;

        if ($service instanceof BaseService) {
            return $service->setPlatform($platform)->setPost($post);
        }

        throw new RuntimeException('Service not found');
    }
}
