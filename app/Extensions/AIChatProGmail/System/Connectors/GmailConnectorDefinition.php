<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGmail\System\Connectors;

use App\Extensions\AIChatPro\System\Connectors\ConnectorDefinition;
use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProGmail\System\Actions\Gmail\GetEmailAction;
use App\Extensions\AIChatProGmail\System\Actions\Gmail\SearchEmailsAction;
use App\Extensions\AIChatProGmail\System\Gmail\GmailClientFactory;
use App\Extensions\AIChatProGmail\System\OAuth\GmailOAuth;
use Illuminate\Support\Facades\Log;
use Throwable;

class GmailConnectorDefinition implements ConnectorDefinition
{
    public function __construct(
        private readonly SearchEmailsAction $searchAction,
        private readonly GetEmailAction $getAction,
        private readonly GmailClientFactory $clientFactory,
        private readonly GmailOAuth $oauth,
    ) {}

    public function key(): string
    {
        return 'gmail';
    }

    public function label(): string
    {
        return 'Gmail';
    }

    public function description(): string
    {
        return __('Give AI access to your inbox to summarize emails, draft responses, and provide personalized assistance.');
    }

    public function icon(): string
    {
        return 'ai-chat-pro-gmail::icon.index';
    }

    public function redirectRoute(): string
    {
        return route('dashboard.user.ai-chat-pro.connectors.gmail.redirect');
    }

    public function isEnabled(): bool
    {
        return (bool) setting('ai_chat_pro_connector_gmail_enabled', '1');
    }

    public function tools(AIChatProConnector $connector, string $engine): array
    {
        $searchSchema = [
            'type'       => 'object',
            'properties' => [
                'query'       => [
                    'type'        => 'string',
                    'description' => 'Gmail search query. Supports operators like `from:`, `to:`, `subject:`, `is:unread`, `has:attachment`, `newer_than:7d`.',
                ],
                'max_results' => [
                    'type'        => 'integer',
                    'description' => 'How many emails to return (1-20).',
                    'minimum'     => 1,
                    'maximum'     => 20,
                ],
            ],
            'required'   => ['query'],
        ];

        $getSchema = [
            'type'       => 'object',
            'properties' => [
                'message_id' => [
                    'type'        => 'string',
                    'description' => 'The Gmail message ID returned from connector_gmail_search_emails.',
                ],
            ],
            'required'   => ['message_id'],
        ];

        return match ($engine) {
            'open_ai' => [
                [
                    'type'        => 'function',
                    'name'        => 'connector_gmail_search_emails',
                    'description' => 'Search the user\'s Gmail inbox. Returns subjects, senders, snippets, and IDs.',
                    'parameters'  => $searchSchema,
                ],
                [
                    'type'        => 'function',
                    'name'        => 'connector_gmail_get_email',
                    'description' => 'Fetch the full body of a single Gmail message by id.',
                    'parameters'  => $getSchema,
                ],
            ],
            'anthropic' => [
                [
                    'name'         => 'connector_gmail_search_emails',
                    'description'  => 'Search the user\'s Gmail inbox. Returns subjects, senders, snippets, and IDs.',
                    'input_schema' => $searchSchema,
                ],
                [
                    'name'         => 'connector_gmail_get_email',
                    'description'  => 'Fetch the full body of a single Gmail message by id.',
                    'input_schema' => $getSchema,
                ],
            ],
            'gemini' => [
                [
                    'name'        => 'connector_gmail_search_emails',
                    'description' => 'Search the user\'s Gmail inbox. Returns subjects, senders, snippets, and IDs.',
                    'parameters'  => $searchSchema,
                ],
                [
                    'name'        => 'connector_gmail_get_email',
                    'description' => 'Fetch the full body of a single Gmail message by id.',
                    'parameters'  => $getSchema,
                ],
            ],
            default => [],
        };
    }

    public function handleToolCall(string $functionName, array $arguments, AIChatProConnector $connector): array
    {
        return match ($functionName) {
            'connector_gmail_search_emails' => $this->searchAction->execute($arguments, $connector),
            'connector_gmail_get_email'     => $this->getAction->execute($arguments, $connector),
            default                         => ['error' => 'Unknown Gmail tool: ' . $functionName],
        };
    }

    public function systemPromptHint(AIChatProConnector $connector): ?string
    {
        $email = $connector->getCredential('email');

        return 'Gmail (' . ($email ?: 'connected') . ') — call connector_gmail_search_emails to find messages, then connector_gmail_get_email for a specific message body.';
    }

    public function meta(AIChatProConnector $connector): array
    {
        return [
            'name' => 'Gmail',
            'key'  => 'gmail',
            'icon' => 'tabler-brand-gmail',
        ];
    }

    public function details(): array
    {
        return [
            'type'    => 'API',
            'author'  => 'Google',
            'uuid'    => 'f8405590-5602-4fee-bfd6-f221623e6f72',
            'website' => 'https://mail.google.com',
        ];
    }

    public function permissions(): array
    {
        return [
            __('Read your Gmail messages and threads (read-only).'),
            __('See the labels and folders in your inbox.'),
            __('See your basic Google profile (name, email, picture).'),
        ];
    }

    public function accessOptions(AIChatProConnector $connector): array
    {
        $options = [];

        try {
            $gmail = $this->clientFactory->make($connector);
            $labelsResult = $gmail->users_labels->listUsersLabels('me');

            foreach ($labelsResult->getLabels() ?? [] as $label) {
                $type = (string) $label->getType();
                $id = (string) $label->getId();
                $name = (string) $label->getName();

                $options[] = [
                    'id'   => $id,
                    'name' => $name,
                    'meta' => $type === 'system' ? __('System') : __('Custom'),
                ];
            }
        } catch (Throwable $exception) {
            Log::warning('[connectors] Gmail accessOptions failed', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
        }

        return [
            'type'    => 'labels',
            'label'   => __('Labels'),
            'options' => $options,
        ];
    }

    public function revokeToken(AIChatProConnector $connector): void
    {
        $token = $connector->getCredential('refresh_token') ?: $connector->getCredential('access_token');

        if (! $token) {
            return;
        }

        try {
            $this->oauth->revokeToken((string) $token);
        } catch (Throwable $exception) {
            Log::warning('[connectors] Gmail revokeToken failed', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
        }
    }
}
