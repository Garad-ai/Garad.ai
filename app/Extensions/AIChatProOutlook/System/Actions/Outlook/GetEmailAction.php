<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProOutlook\System\Actions\Outlook;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProOutlook\System\Graph\GraphClientFactory;
use Throwable;

class GetEmailAction
{
    public function __construct(private readonly GraphClientFactory $factory) {}

    /**
     * @param  array{message_id?: string}  $arguments
     */
    public function execute(array $arguments, AIChatProConnector $connector): array
    {
        $messageId = trim((string) ($arguments['message_id'] ?? ''));

        if ($messageId === '') {
            return ['error' => 'message_id is required.'];
        }

        try {
            $client = $this->factory->make($connector);
            $response = $client->get('/me/messages/' . rawurlencode($messageId), [
                '$select' => 'id,subject,from,toRecipients,receivedDateTime,bodyPreview,body',
            ]);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        if (! $response->successful()) {
            return ['error' => 'Outlook request failed: ' . $response->status()];
        }

        $detail = $response->json();

        $to = collect((array) data_get($detail, 'toRecipients', []))
            ->map(fn ($recipient) => (string) data_get($recipient, 'emailAddress.address', ''))
            ->filter()
            ->implode(', ');

        $bodyContent = (string) data_get($detail, 'body.content', '');
        $bodyType = (string) data_get($detail, 'body.contentType', 'text');

        if ($bodyType === 'html') {
            $bodyContent = trim(html_entity_decode(strip_tags($bodyContent), ENT_QUOTES|ENT_HTML5, 'UTF-8'));
        }

        return [
            'id'      => (string) data_get($detail, 'id', ''),
            'subject' => (string) data_get($detail, 'subject', ''),
            'from'    => (string) (data_get($detail, 'from.emailAddress.address') ?? data_get($detail, 'from.emailAddress.name', '')),
            'to'      => $to,
            'date'    => (string) data_get($detail, 'receivedDateTime', ''),
            'snippet' => (string) data_get($detail, 'bodyPreview', ''),
            'body'    => mb_substr($bodyContent, 0, 12000),
        ];
    }
}
