<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Notifications;

use App\Extensions\AIChatPro\System\Connectors\ConnectorRegistry;
use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConnectorTokenInvalidatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected AIChatProConnector $connector) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $this->connectorLabel();

        return (new MailMessage)
            ->subject(__(':label connection needs your attention', ['label' => $label]))
            ->greeting(__('Hello!'))
            ->line(__('Your :label connector is no longer able to access your account. This usually happens when access was revoked or the session expired.', ['label' => $label]))
            ->line(__('To keep using AI Chat Pro with this account, please reconnect it.'))
            ->action(__('Open AI Chat Pro'), route('chat.pro'))
            ->line(__('No data has been lost; you simply need to grant access again.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'connector_id'   => $this->connector->id,
            'connector_type' => $this->connector->type,
            'connector_uuid' => $this->connector->uuid,
            'message'        => __('Your :label connector needs to be reconnected.', ['label' => $this->connectorLabel()]),
            'action_url'     => route('chat.pro'),
        ];
    }

    private function connectorLabel(): string
    {
        $definition = app(ConnectorRegistry::class)->get($this->connector->type);

        return $definition?->label() ?? $this->connector->type;
    }
}
