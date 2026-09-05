<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGoogleCalendar\System\Connectors;

use App\Extensions\AIChatPro\System\Connectors\ConnectorDefinition;
use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProGoogleCalendar\System\Actions\Calendar\FindEventsAction;
use App\Extensions\AIChatProGoogleCalendar\System\Actions\Calendar\ListUpcomingEventsAction;
use App\Extensions\AIChatProGoogleCalendar\System\Calendar\CalendarClientFactory;
use App\Extensions\AIChatProGoogleCalendar\System\OAuth\GoogleCalendarOAuth;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleCalendarConnectorDefinition implements ConnectorDefinition
{
    public function __construct(
        private readonly ListUpcomingEventsAction $listAction,
        private readonly FindEventsAction $findAction,
        private readonly CalendarClientFactory $clientFactory,
        private readonly GoogleCalendarOAuth $oauth,
    ) {}

    public function key(): string
    {
        return 'google_calendar';
    }

    public function label(): string
    {
        return 'Google Calendar';
    }

    public function description(): string
    {
        return __('Enable AI to understand your schedule and help plan your day more effectively.');
    }

    public function icon(): string
    {
        return 'ai-chat-pro-google-calendar::icon.index';
    }

    public function redirectRoute(): string
    {
        return route('dashboard.user.ai-chat-pro.connectors.google-calendar.redirect');
    }

    public function isEnabled(): bool
    {
        return (bool) setting('ai_chat_pro_connector_google_calendar_enabled', '1');
    }

    public function tools(AIChatProConnector $connector, string $engine): array
    {
        $listSchema = [
            'type'       => 'object',
            'properties' => [
                'max_results'       => [
                    'type'        => 'integer',
                    'description' => 'How many upcoming events to return (1-20).',
                    'minimum'     => 1,
                    'maximum'     => 20,
                ],
                'time_horizon_days' => [
                    'type'        => 'integer',
                    'description' => 'How many days into the future to look (1-90, default 7).',
                    'minimum'     => 1,
                    'maximum'     => 90,
                ],
            ],
            'required'   => [],
        ];

        $findSchema = [
            'type'       => 'object',
            'properties' => [
                'query'       => [
                    'type'        => 'string',
                    'description' => 'Free-text search term matched against event summary, description, location, and attendees.',
                ],
                'max_results' => [
                    'type'        => 'integer',
                    'description' => 'How many events to return (1-20).',
                    'minimum'     => 1,
                    'maximum'     => 20,
                ],
            ],
            'required'   => ['query'],
        ];

        return match ($engine) {
            'open_ai' => [
                [
                    'type'        => 'function',
                    'name'        => 'connector_google_calendar_list_upcoming_events',
                    'description' => 'List the user\'s upcoming Google Calendar events ordered by start time.',
                    'parameters'  => $listSchema,
                ],
                [
                    'type'        => 'function',
                    'name'        => 'connector_google_calendar_find_events',
                    'description' => 'Search the user\'s Google Calendar by free-text query across a wide window.',
                    'parameters'  => $findSchema,
                ],
            ],
            'anthropic' => [
                [
                    'name'         => 'connector_google_calendar_list_upcoming_events',
                    'description'  => 'List the user\'s upcoming Google Calendar events ordered by start time.',
                    'input_schema' => $listSchema,
                ],
                [
                    'name'         => 'connector_google_calendar_find_events',
                    'description'  => 'Search the user\'s Google Calendar by free-text query across a wide window.',
                    'input_schema' => $findSchema,
                ],
            ],
            'gemini' => [
                [
                    'name'        => 'connector_google_calendar_list_upcoming_events',
                    'description' => 'List the user\'s upcoming Google Calendar events ordered by start time.',
                    'parameters'  => $listSchema,
                ],
                [
                    'name'        => 'connector_google_calendar_find_events',
                    'description' => 'Search the user\'s Google Calendar by free-text query across a wide window.',
                    'parameters'  => $findSchema,
                ],
            ],
            default => [],
        };
    }

    public function handleToolCall(string $functionName, array $arguments, AIChatProConnector $connector): array
    {
        return match ($functionName) {
            'connector_google_calendar_list_upcoming_events' => $this->listAction->execute($arguments, $connector),
            'connector_google_calendar_find_events'          => $this->findAction->execute($arguments, $connector),
            default                                          => ['error' => 'Unknown Google Calendar tool: ' . $functionName],
        };
    }

    public function systemPromptHint(AIChatProConnector $connector): ?string
    {
        $email = $connector->getCredential('email');

        return 'Google Calendar (' . ($email ?: 'connected') . ') — call connector_google_calendar_list_upcoming_events for what is coming up, or connector_google_calendar_find_events to search by keyword.';
    }

    public function meta(AIChatProConnector $connector): array
    {
        return [
            'name' => 'Google Calendar',
            'key'  => 'google_calendar',
            'icon' => 'tabler-calendar-event',
        ];
    }

    public function details(): array
    {
        return [
            'type'    => 'API',
            'author'  => 'Google',
            'uuid'    => 'e7c93bd4-65a2-4e9f-a4b6-8c1d2f3a4571',
            'website' => 'https://calendar.google.com',
        ];
    }

    public function permissions(): array
    {
        return [
            __('Read your Google Calendar events (read-only).'),
            __('See your list of calendars.'),
            __('See your basic Google profile (name, email, picture).'),
        ];
    }

    public function accessOptions(AIChatProConnector $connector): array
    {
        $options = [];

        try {
            $calendar = $this->clientFactory->make($connector);
            $result = $calendar->calendarList->listCalendarList();

            foreach ($result->getItems() ?? [] as $item) {
                $options[] = [
                    'id'   => (string) $item->getId(),
                    'name' => (string) ($item->getSummary() ?: $item->getId()),
                    'meta' => (string) ($item->getPrimary() ? __('Primary') : ($item->getAccessRole() ?? '')),
                ];
            }
        } catch (Throwable $exception) {
            Log::warning('[connectors] Google Calendar accessOptions failed', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
        }

        return [
            'type'    => 'calendars',
            'label'   => __('Calendars'),
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
            Log::warning('[connectors] Google Calendar revokeToken failed', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
        }
    }
}
