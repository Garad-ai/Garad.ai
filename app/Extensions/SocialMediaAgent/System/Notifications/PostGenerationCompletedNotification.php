<?php

namespace App\Extensions\SocialMediaAgent\System\Notifications;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PostGenerationCompletedNotification extends Notification
{
    use Queueable;

    protected SocialMediaAgent $agent;

    protected int $generatedCount;

    protected int $failedCount;

    public function __construct(SocialMediaAgent $agent, int $generatedCount, int $failedCount = 0)
    {
        $this->agent = $agent;
        $this->generatedCount = $generatedCount;
        $this->failedCount = $failedCount;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'agent_id'        => $this->agent->id,
            'agent_name'      => $this->agent->name,
            'generated_count' => $this->generatedCount,
            'failed_count'    => $this->failedCount,
            'message'         => $this->getMessage(),
            'action_url'      => route('dashboard.user.social-media.agent.show', $this->agent),
            'type'            => 'post_generation_completed',
        ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'agent_id'        => $this->agent->id,
            'agent_name'      => $this->agent->name,
            'generated_count' => $this->generatedCount,
            'failed_count'    => $this->failedCount,
            'message'         => $this->getMessage(),
            'action_url'      => route('dashboard.user.social-media.agent.show', $this->agent),
        ];
    }

    /**
     * Get the notification message
     */
    protected function getMessage(): string
    {
        if ($this->failedCount > 0) {
            return "Post generation completed for '{$this->agent->name}': {$this->generatedCount} posts created, {$this->failedCount} failed";
        }

        return "Post generation completed for '{$this->agent->name}': {$this->generatedCount} posts ready for review";
    }
}
