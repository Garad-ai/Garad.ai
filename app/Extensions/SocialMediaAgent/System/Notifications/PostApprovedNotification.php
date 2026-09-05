<?php

namespace App\Extensions\SocialMediaAgent\System\Notifications;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostApprovedNotification extends Notification
{
    use Queueable;

    protected SocialMediaAgentPost $post;

    public function __construct(SocialMediaAgentPost $post)
    {
        $this->post = $post;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Social Media Post Approved')
            ->greeting('Hello!')
            ->line('Your social media post has been approved and is ready for publishing.')
            ->line('Agent: ' . $this->post->agent->name)
            ->line('Scheduled for: ' . $this->post->scheduled_at->format('M d, Y \a\t h:i A'))
            ->action('View Post', route('dashboard.user.social-media.agent.show', $this->post->agent))
            ->line('The post will be published automatically at the scheduled time.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'post_id'      => $this->post->id,
            'agent_id'     => $this->post->agent_id,
            'agent_name'   => $this->post->agent->name,
            'scheduled_at' => $this->post->scheduled_at->toIso8601String(),
            'message'      => "Post for '{$this->post->agent->name}' has been approved",
            'action_url'   => route('dashboard.user.social-media.agent.show', $this->post->agent),
        ];
    }
}
