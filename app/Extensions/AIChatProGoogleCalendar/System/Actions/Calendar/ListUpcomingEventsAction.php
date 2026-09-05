<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGoogleCalendar\System\Actions\Calendar;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProGoogleCalendar\System\Calendar\CalendarClientFactory;
use Google\Service\Calendar\Event;
use Throwable;

class ListUpcomingEventsAction
{
    public function __construct(private readonly CalendarClientFactory $factory) {}

    /**
     * @param  array{max_results?: int, time_horizon_days?: int}  $arguments
     */
    public function execute(array $arguments, AIChatProConnector $connector): array
    {
        $maxResults = max(1, min((int) ($arguments['max_results'] ?? 10), 20));
        $timeHorizonDays = max(1, min((int) ($arguments['time_horizon_days'] ?? 7), 90));

        $timeMin = now()->toRfc3339String();
        $timeMax = now()->addDays($timeHorizonDays)->toRfc3339String();

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
