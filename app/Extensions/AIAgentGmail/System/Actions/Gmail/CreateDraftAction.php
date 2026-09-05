<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Actions\Gmail;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentGmail\System\Gmail\GmailClientFactory;
use App\Extensions\AIAgentGmail\System\Gmail\MimeMessageBuilder;
use Google\Service\Gmail\Draft;

class CreateDraftAction implements ActionInterface
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * Config keys:
     *   - to              (string) : recipient email address (required)
     *   - subject         (string) : email subject (required)
     *   - body            (string) : email body (required)
     *   - from            (string) : sender override (optional)
     *   - store_output_as (string) : context key (default: created_draft)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'created_draft';

        $gmail = $this->factory->make($workflow->user_id);
        $message = MimeMessageBuilder::build(
            to: $this->interpolate($config['to'] ?? '', $context),
            subject: $this->interpolate($config['subject'] ?? '', $context),
            body: $this->interpolate($config['body'] ?? '', $context),
            from: isset($config['from']) ? $this->interpolate($config['from'], $context) : null,
        );

        $draft = new Draft;
        $draft->setMessage($message);

        $created = $gmail->users_drafts->create('me', $draft);

        return array_merge($context, [
            $storeOutputAs => [
                'draft_id'   => $created->getId(),
                'message_id' => $created->getMessage()?->getId(),
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
