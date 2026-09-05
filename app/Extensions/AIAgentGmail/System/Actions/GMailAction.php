<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\AddLabelToEmailAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\ArchiveEmailAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\CreateDraftAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\CreateDraftReplyAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\CreateLabelAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\DeleteEmailAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\FindEmailAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\GetAttachmentByFilenameAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\RemoveLabelFromConversationAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\RemoveLabelFromEmailAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\ReplyToEmailAction;
use App\Extensions\AIAgentGmail\System\Actions\Gmail\SendEmailAction;
use Exception;

class GMailAction implements AIAgentActionInterface
{
    public function __construct(
        private readonly AddLabelToEmailAction $addLabelToEmailAction,
        private readonly ArchiveEmailAction $archiveEmailAction,
        private readonly CreateDraftAction $createDraftAction,
        private readonly CreateDraftReplyAction $createDraftReplyAction,
        private readonly CreateLabelAction $createLabelAction,
        private readonly DeleteEmailAction $deleteEmailAction,
        private readonly FindEmailAction $findEmailAction,
        private readonly GetAttachmentByFilenameAction $getAttachmentByFilenameAction,
        private readonly RemoveLabelFromConversationAction $removeLabelFromConversationAction,
        private readonly RemoveLabelFromEmailAction $removeLabelFromEmailAction,
        private readonly ReplyToEmailAction $replyToEmailAction,
        private readonly SendEmailAction $sendEmailAction,
    ) {}

    /**
     * Config keys:
     *   - action_event (string) : sub-operation to perform (required)
     *
     * Per action_event keys:
     *   send_email:                          to, subject, body, from (optional), store_output_as
     *   reply_to_email:                      message_id, body, store_output_as
     *   create_draft:                        to, subject, body, from (optional), store_output_as
     *   create_draft_reply:                  message_id, body, store_output_as
     *   add_label_to_email:                  message_id, label_ids, store_output_as
     *   remove_label_from_email:             message_id, label_ids, store_output_as
     *   remove_label_from_conversation:      thread_id, label_ids, store_output_as
     *   archive_email:                       message_id, store_output_as
     *   delete_email:                        message_id, store_output_as
     *   create_label:                        name, label_list_visibility, message_list_visibility, store_output_as
     *   find_email:                          query, max_results, store_output_as
     *   get_attachment_by_filename:          message_id, filename, store_output_as
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $actionEvent = $config['action_event'] ?? '';

        return match ($actionEvent) {
            'add_label_to_email'             => $this->addLabelToEmailAction->execute($config, $context, $workflow, $run),
            'archive_email'                  => $this->archiveEmailAction->execute($config, $context, $workflow, $run),
            'create_draft'                   => $this->createDraftAction->execute($config, $context, $workflow, $run),
            'create_draft_reply'             => $this->createDraftReplyAction->execute($config, $context, $workflow, $run),
            'create_label'                   => $this->createLabelAction->execute($config, $context, $workflow, $run),
            'delete_email'                   => $this->deleteEmailAction->execute($config, $context, $workflow, $run),
            'find_email'                     => $this->findEmailAction->execute($config, $context, $workflow, $run),
            'get_attachment_by_filename'     => $this->getAttachmentByFilenameAction->execute($config, $context, $workflow, $run),
            'remove_label_from_conversation' => $this->removeLabelFromConversationAction->execute($config, $context, $workflow, $run),
            'remove_label_from_email'        => $this->removeLabelFromEmailAction->execute($config, $context, $workflow, $run),
            'reply_to_email'                 => $this->replyToEmailAction->execute($config, $context, $workflow, $run),
            'send_email'                     => $this->sendEmailAction->execute($config, $context, $workflow, $run),
            default                          => throw new Exception('GMailAction: unknown action_event "' . $actionEvent . '".'),
        };
    }

    public function getCategory(): string
    {
        return 'utilities';
    }

    public function getLabel(): string
    {
        return 'GMail';
    }

    public function getDescription(): string
    {
        return 'Manage your Gmail: send emails, read them, manage labels, drafts, and more.';
    }

    public function getIcon(): string
    {
        return 'tabler-brand-gmail';
    }

    public function getConfigSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['action_event'],
            'properties' => [
                'action_event' => [
                    'type' => 'string',
                    'enum' => [
                        'add_label_to_email',
                        'archive_email',
                        'create_draft',
                        'create_draft_reply',
                        'create_label',
                        'delete_email',
                        'find_email',
                        'get_attachment_by_filename',
                        'remove_label_from_conversation',
                        'remove_label_from_email',
                        'reply_to_email',
                        'send_email',
                    ],
                    'description' => 'The Gmail operation to perform.',
                ],
                'message_id'               => ['type' => 'string', 'description' => 'Gmail message ID.'],
                'thread_id'                => ['type' => 'string', 'description' => 'Gmail thread ID.'],
                'to'                       => ['type' => 'string', 'description' => 'Recipient email address (send_email, create_draft).'],
                'from'                     => ['type' => 'string', 'description' => 'Sender email override (send_email, create_draft).'],
                'subject'                  => ['type' => 'string', 'description' => 'Email subject (send_email, create_draft).'],
                'body'                     => ['type' => 'string', 'description' => 'Email or reply body.'],
                'label_ids'                => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Label IDs to add or remove.'],
                'name'                     => ['type' => 'string', 'description' => 'Label name (create_label).'],
                'label_list_visibility'    => ['type' => 'string', 'enum' => ['labelShow', 'labelShowIfUnread', 'labelHide'], 'description' => 'Label list visibility (create_label).'],
                'message_list_visibility'  => ['type' => 'string', 'enum' => ['show', 'hide'], 'description' => 'Message list visibility (create_label).'],
                'query'                    => ['type' => 'string', 'description' => 'Gmail search query (find_email).'],
                'max_results'              => ['type' => 'integer', 'description' => 'Max results (find_email, default 10).'],
                'return_format'            => ['type' => 'string', 'enum' => ['id', 'subject', 'body', 'sender', 'date', 'full_as_string'], 'description' => 'What to store in context (find_email).'],
                'filename'                 => ['type' => 'string', 'description' => 'Attachment filename (get_attachment_by_filename).'],
                'store_output_as'          => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ];
    }
}
