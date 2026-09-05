<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Actions\Gmail;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentGmail\System\Gmail\GmailClientFactory;
use App\Extensions\AIAgentGmail\System\Gmail\MimeMessageBuilder;
use Google\Service\Gmail\MessagePartHeader;

class ReplyToEmailAction implements ActionInterface
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * Config keys:
     *   - message_id      (string) : Gmail message ID to reply to (required)
     *   - body            (string) : reply body (required)
     *   - store_output_as (string) : context key (default: reply_email)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'reply_email';

        $gmail = $this->factory->make($workflow->user_id);
        $messageId = $config['message_id'] ?? '';

        $original = $gmail->users_messages->get('me', $messageId, ['format' => 'metadata', 'metadataHeaders' => ['Subject', 'From', 'Message-ID']]);

        $headers = collect($original->getPayload()?->getHeaders() ?? [])
            ->keyBy(fn (MessagePartHeader $h) => $h->getName());

        $subject = 'Re: ' . ltrim((string) ($headers['Subject']?->getValue() ?? ''), 'Re: ');
        $to = (string) ($headers['From']?->getValue() ?? '');
        $inReplyTo = (string) ($headers['Message-ID']?->getValue() ?? '');
        $threadId = $original->getThreadId();

        $message = MimeMessageBuilder::build(
            to: $to,
            subject: $subject,
            body: $this->interpolate($config['body'] ?? '', $context),
            inReplyTo: $inReplyTo,
            threadId: $threadId,
        );

        $sent = $gmail->users_messages->send('me', $message);

        return array_merge($context, [
            $storeOutputAs => [
                'message_id' => $sent->getId(),
                'thread_id'  => $sent->getThreadId(),
            ],
        ]);
    }

    private function interpolate(string $template, array $context): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($context): string {
            $value = $context[$matches[1]] ?? $matches[0];

            if (is_array($value)) {
                return json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?: $matches[0];
            }

            return (string) $value;
        }, $template);
    }
}
