<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProOutlook\System\Connectors;

use App\Extensions\AIChatPro\System\Connectors\ConnectorDefinition;
use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProOutlook\System\Actions\Outlook\GetEmailAction;
use App\Extensions\AIChatProOutlook\System\Actions\Outlook\SearchEmailsAction;
use App\Extensions\AIChatProOutlook\System\Graph\GraphClientFactory;
use App\Extensions\AIChatProOutlook\System\OAuth\OutlookOAuth;
use Illuminate\Support\Facades\Log;
use Throwable;

class OutlookConnectorDefinition implements ConnectorDefinition
{
    public function __construct(
        private readonly SearchEmailsAction $searchAction,
        private readonly GetEmailAction $getAction,
        private readonly GraphClientFactory $clientFactory,
        private readonly OutlookOAuth $oauth,
    ) {}

    public function key(): string
    {
        return 'outlook';
    }

    public function label(): string
    {
        return 'Outlook';
    }

    public function description(): string
    {
        return __('Connect your Microsoft workspace to let AI manage emails and communications from one place.');
    }

    public function icon(): string
    {
        return 'ai-chat-pro-outlook::icon.index';
    }

    public function redirectRoute(): string
    {
        return route('dashboard.user.ai-chat-pro.connectors.outlook.redirect');
    }

    public function isEnabled(): bool
    {
        return (bool) setting('ai_chat_pro_connector_outlook_enabled', '1');
    }

    public function tools(AIChatProConnector $connector, string $engine): array
    {
        $searchSchema = [
            'type'       => 'object',
            'properties' => [
                'query'       => [
                    'type'        => 'string',
                    'description' => 'Optional free-text search query passed to Microsoft Graph $search (matches subject, body, sender, recipients). Omit or pass an empty string to list the most recent messages.',
                ],
                'max_results' => [
                    'type'        => 'integer',
                    'description' => 'How many emails to return (1-20).',
                    'minimum'     => 1,
                    'maximum'     => 20,
                ],
            ],
            'required'   => [],
        ];

        $getSchema = [
            'type'       => 'object',
            'properties' => [
                'message_id' => [
                    'type'        => 'string',
                    'description' => 'The Outlook message ID returned from connector_outlook_search_emails.',
                ],
            ],
            'required'   => ['message_id'],
        ];

        return match ($engine) {
            'open_ai' => [
                [
                    'type'        => 'function',
                    'name'        => 'connector_outlook_search_emails',
                    'description' => 'Search the user\'s Outlook mailbox. Returns subjects, senders, body previews, and IDs.',
                    'parameters'  => $searchSchema,
                ],
                [
                    'type'        => 'function',
                    'name'        => 'connector_outlook_get_email',
                    'description' => 'Fetch the full body of a single Outlook message by id.',
                    'parameters'  => $getSchema,
                ],
            ],
            'anthropic' => [
                [
                    'name'         => 'connector_outlook_search_emails',
                    'description'  => 'Search the user\'s Outlook mailbox. Returns subjects, senders, body previews, and IDs.',
                    'input_schema' => $searchSchema,
                ],
                [
                    'name'         => 'connector_outlook_get_email',
                    'description'  => 'Fetch the full body of a single Outlook message by id.',
                    'input_schema' => $getSchema,
                ],
            ],
            'gemini' => [
                [
                    'name'        => 'connector_outlook_search_emails',
                    'description' => 'Search the user\'s Outlook mailbox. Returns subjects, senders, body previews, and IDs.',
                    'parameters'  => $searchSchema,
                ],
                [
                    'name'        => 'connector_outlook_get_email',
                    'description' => 'Fetch the full body of a single Outlook message by id.',
                    'parameters'  => $getSchema,
                ],
            ],
            default => [],
        };
    }

    public function handleToolCall(string $functionName, array $arguments, AIChatProConnector $connector): array
    {
        return match ($functionName) {
            'connector_outlook_search_emails' => $this->searchAction->execute($arguments, $connector),
            'connector_outlook_get_email'     => $this->getAction->execute($arguments, $connector),
            default                           => ['error' => 'Unknown Outlook tool: ' . $functionName],
        };
    }

    public function systemPromptHint(AIChatProConnector $connector): ?string
    {
        $email = $connector->getCredential('email');

        return 'Outlook (' . ($email ?: 'connected') . ') — call connector_outlook_search_emails to find messages, then connector_outlook_get_email for a specific message body.';
    }

    public function meta(AIChatProConnector $connector): array
    {
        return [
            'name' => 'Outlook',
            'key'  => 'outlook',
            'icon' => 'tabler-brand-office',
        ];
    }

    public function details(): array
    {
        return [
            'type'    => 'API',
            'author'  => 'Microsoft',
            'uuid'    => 'b8a5fd23-7311-4cba-a4d1-2c4e1a0c9a01',
            'website' => 'https://outlook.live.com',
        ];
    }

    public function permissions(): array
    {
        return [
            __('Read your Outlook mail messages (read-only).'),
            __('See the folders in your mailbox.'),
            __('See your basic Microsoft profile (name, email).'),
        ];
    }

    public function accessOptions(AIChatProConnector $connector): array
    {
        $options = [];

        try {
            $client = $this->clientFactory->make($connector);
            $response = $client->get('/me/mailFolders', [
                '$top'    => 50,
                '$select' => 'id,displayName,totalItemCount',
            ]);

            foreach ((array) data_get($response->json(), 'value', []) as $folder) {
                $options[] = [
                    'id'   => (string) data_get($folder, 'id', ''),
                    'name' => (string) data_get($folder, 'displayName', ''),
                    'meta' => (string) data_get($folder, 'totalItemCount', ''),
                ];
            }
        } catch (Throwable $exception) {
            Log::warning('[connectors] Outlook accessOptions failed', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
        }

        return [
            'type'    => 'folders',
            'label'   => __('Folders'),
            'options' => $options,
        ];
    }

    public function revokeToken(AIChatProConnector $connector): void
    {
        $accessToken = $connector->getCredential('access_token');

        if (! $accessToken) {
            return;
        }

        try {
            $this->oauth->revokeToken((string) $accessToken);
        } catch (Throwable $exception) {
            Log::warning('[connectors] Outlook revokeToken failed (this is expected when the User.RevokeSessions.All scope is not granted)', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
        }
    }
}
