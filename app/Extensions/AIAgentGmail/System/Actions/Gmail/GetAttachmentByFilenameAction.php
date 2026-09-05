<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Actions\Gmail;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentGmail\System\Gmail\GmailClientFactory;
use Google\Service\Gmail\MessagePart;
use RuntimeException;

class GetAttachmentByFilenameAction implements ActionInterface
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * Config keys:
     *   - message_id      (string) : Gmail message ID (required)
     *   - filename        (string) : attachment filename to find (required)
     *   - store_output_as (string) : context key (default: attachment)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'attachment';

        $gmail     = $this->factory->make($workflow->user_id);
        $messageId = $config['message_id'] ?? '';
        $filename  = $config['filename'] ?? '';

        $message = $gmail->users_messages->get('me', $messageId, ['format' => 'full']);

        $attachmentPart = $this->findAttachment($message->getPayload()?->getParts() ?? [], $filename);

        if (! $attachmentPart) {
            throw new RuntimeException('Attachment "' . $filename . '" not found in message ' . $messageId . '.');
        }

        $attachmentId   = $attachmentPart->getBody()->getAttachmentId();
        $attachmentData = $gmail->users_messages_attachments->get('me', $messageId, $attachmentId);
        $data           = strtr($attachmentData->getData() ?? '', '-_', '+/');

        return array_merge($context, [
            $storeOutputAs => [
                'filename'  => $filename,
                'mime_type' => $attachmentPart->getMimeType(),
                'size'      => $attachmentPart->getBody()->getSize(),
                'data'      => $data,
            ],
        ]);
    }

    /** @param MessagePart[] $parts */
    private function findAttachment(array $parts, string $filename): ?MessagePart
    {
        foreach ($parts as $part) {
            if (strtolower((string) $part->getFilename()) === strtolower($filename)) {
                return $part;
            }

            $nested = $this->findAttachment($part->getParts() ?? [], $filename);

            if ($nested) {
                return $nested;
            }
        }

        return null;
    }
}
