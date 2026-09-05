<?php

namespace App\Extensions\SocialMediaAgent\System\Notifications;

use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgentPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostPublishedNotification extends Notification
{
    use Queueable;

    protected SocialMediaAgentPost $post;

    protected bool $isSuccess;

    protected ?string $errorMessage;

    public function __construct(SocialMediaAgentPost $post, bool $isSuccess = true, ?string $errorMessage = null)
    {
        $this->post = $post;
        $this->isSuccess = $isSuccess;
        $this->errorMessage = $errorMessage;
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
        if ($this->isSuccess) {
            return (new MailMessage)
                ->subject('Social Media Post Published Successfully')
                ->greeting('Great News!')
                ->line('Your social media post has been published successfully.')
                ->line('Agent: ' . $this->post->agent->name)
                ->line('Published at: ' . $this->post->published_at->format('M d, Y \a\t h:i A'))
                ->action('View Post', route('dashboard.user.social-media.agent.show', $this->post->agent))
                ->line('You can track the performance of this post in your analytics dashboard.');
        }

        return (new MailMessage)
            ->subject('Social Media Post Failed to Publish')
            ->greeting('Publishing Failed')
            ->error()
            ->line('Your social media post failed to publish.')
            ->line('Agent: ' . $this->post->agent->name)
            ->line('Error: ' . ($this->errorMessage ?? 'Unknown error'))
            ->action('View Post', route('dashboard.user.social-media.agent.show', $this->post->agent))
            ->line('Please check your platform credentials and try again.');
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
            'published_at' => $this->post->published_at?->toIso8601String(),
            'is_success'   => $this->isSuccess,
            'message'      => $this->isSuccess
                ? "Post for '{$this->post->agent->name}' has been published successfully"
                : "Post for '{$this->post->agent->name}' failed to publish",
            'error_message' => $this->errorMessage,
            'action_url'    => route('dashboard.user.social-media.agent.show', $this->post->agent),
        ];
    }
}
