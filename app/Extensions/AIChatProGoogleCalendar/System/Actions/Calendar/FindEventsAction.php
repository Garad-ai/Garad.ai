<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGoogleCalendar\System\Actions\Calendar;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProGoogleCalendar\System\Calendar\CalendarClientFactory;
use Google\Service\Calendar\Event;
use Throwable;

class FindEventsAction
{
    public function __construct(private readonly CalendarClientFactory $factory) {}

    /**
     * @param  array{query?: string, max_results?: int}  $arguments
     */
    public function execute(array $arguments, AIChatProConnector $connector): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            return ['error' => 'query is required.'];
        }

        $maxResults = max(1, min((int) ($arguments['max_results'] ?? 10), 20));

        $timeMin = $connector->selectedRecentOnly()
            ? now()->subDays(30)->toRfc3339String()
            : now()->subDays(30)->toRfc3339String();
        $timeMax = now()->addDays(90)->toRfc3339String();

        $allowedCalendars = $connector->selectedAccessFor('calendars') ?? ['primary'];

        try {
            $calendar = $this->factory->make($connector);
            $events = [];

            foreach ($allowedCalendars as $calendarId) {
                $result = $calendar->events->listEvents($calendarId, [
                    'timeMin'      => $timeMin,
                    'timeMax'      => $timeMax,
                    'maxResults'   => $maxResults,
                    'singleEvents' => true,
                    'orderBy'      => 'startTime',
                    'q'            => $query,
                ]);

                foreach ($result->getItems() ?? [] as $event) {
                    $events[] = $this->formatEvent($event);
                    if (count($events) >= $maxResults) {
                        break 2;
                    }
                }
            }
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        return [
            'query'    => $query,
            'time_min' => $timeMin,
            'time_max' => $timeMax,
            'count'    => count($events),
            'events'   => $events,
        ];
    }

    /**
     * @return array{id: string, summary: string, start: string, end: string, location: string, description: string, attendees: array<int, string>, html_link: string, all_day: bool}
     */
    private function formatEvent(Event $event): array
    {
        $start = $event->getStart();
        $end = $event->getEnd();

        $startValue = $start?->getDateTime() ?? $start?->getDate() ?? '';
        $endValue = $end?->getDateTime() ?? $end?->getDate() ?? '';
        $allDay = ! $start?->getDateTime() && (bool) $start?->getDate();

        $attendees = [];
        foreach ($event->getAttendees() ?? [] as $attendee) {
            $email = $attendee->getEmail();
            if ($email) {
                $attendees[] = $email;
            }
        }

        $description = (string) ($event->getDescription() ?? '');

        return [
            'id'          => (string) $event->getId(),
            'summary'     => (string) ($event->getSummary() ?? ''),
            'start'       => (string) $startValue,
            'end'         => (string) $endValue,
            'location'    => (string) ($event->getLocation() ?? ''),
            'description' => mb_substr($description, 0, 500),
            'attendees'   => $attendees,
            'html_link'   => (string) ($event->getHtmlLink() ?? ''),
            'all_day'     => $allDay,
        ];
    }
}
