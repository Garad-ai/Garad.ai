<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGmail\System\Actions\Gmail;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProGmail\System\Gmail\GmailClientFactory;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Throwable;

class GetEmailAction
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * @param  array{message_id?: string}  $arguments
     */
    public function execute(array $arguments, AIChatProConnector $connector): array
    {
        $messageId = (string) ($arguments['message_id'] ?? '');

        if ($messageId === '') {
            return ['error' => 'message_id is required.'];
        }

        try {
            $gmail = $this->factory->make($connector);
            $detail = $gmail->users_messages->get('me', $messageId, ['format' => 'full']);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        $headers = collect($detail->getPayload()?->getHeaders() ?? [])
            ->keyBy(fn ($h) => strtolower($h->getName()));

        $body = $this->extractBody($detail);

        return [
            'id'      => $detail->getId(),
            'subject' => $headers->get('subject')?->getValue() ?? '',
            'from'    => $headers->get('from')?->getValue() ?? '',
            'to'      => $headers->get('to')?->getValue() ?? '',
            'date'    => $headers->get('date')?->getValue() ?? '',
            'snippet' => $detail->getSnippet() ?? '',
            'body'    => mb_substr($body, 0, 12000),
        ];
    }

    private function extractBody(Message $message): string
    {
        $payload = $message->getPayload();

        if (! $payload) {
            return '';
        }

        $data = $payload->getBody()?->getData();

        if ($data) {
            return base64_decode(strtr($data, '-_', '+/'));
        }

        return $this->findTextPart($payload->getParts() ?? []);
    }

    /** @param  MessagePart[]  $parts */
    private function findTextPart(array $parts): string
    {
        foreach ($parts as $part) {
            if ($part->getMimeType() === 'text/plain') {
                $data = $part->getBody()?->getData();

                if ($data) {
                    return base64_decode(strtr($data, '-_', '+/'));
                }
            }

            $nested = $this->findTextPart($part->getParts() ?? []);

            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }
}
