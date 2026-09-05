<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Booking\Providers;

use App\Extensions\PhoneCallAgent\System\Booking\Contracts\BookingProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalendlyBookingProvider implements BookingProviderInterface
{
    private const BASE_URL = 'https://api.calendly.com';

    public function __construct(
        private readonly string $eventTypeUri,
        private readonly string $apiKey,
    ) {}

    public function checkAvailability(string $startDate, string $endDate): array
    {
        $query = http_build_query([
            'event_type' => $this->eventTypeUri,
            'start_time' => $startDate,
            'end_time'   => $endDate,
        ]);

        Log::info('[Calendly] checkAvailability', ['eventTypeUri' => $this->eventTypeUri, 'start' => $startDate, 'end' => $endDate]);

        $response = $this->get('/event_type_available_times?' . $query);

        $values = $response['collection'] ?? [];

        $slots = array_map(fn ($v) => $v['start_time'] ?? null, $values);
        $result = array_values(array_filter($slots));

        Log::info('[Calendly] checkAvailability result', ['count' => count($result)]);

        return $result;
    }

    public function createBooking(string $slot, string $name, string $email, string $timezone, ?string $phone = null): array
    {
        $invitee = [
            'name'     => $name,
            'email'    => $email,
            'timezone' => $timezone,
        ];

        if ($phone !== null && $phone !== '') {
            $invitee['text_reminder_number'] = $phone;
        }

        $payload = [
            'event_type' => $this->eventTypeUri,
            'start_time' => $slot,
            'invitee'    => $invitee,
        ];

        Log::info('[Calendly] createBooking', ['eventTypeUri' => $this->eventTypeUri, 'slot' => $slot, 'email' => $email]);

        $response = $this->post('/invitees', $payload);

        if (isset($response['message'])) {
            Log::error('[Calendly] createBooking error', ['message' => $response['message']]);

            return [];
        }

        // Use scheduled event URI as uid so cancel/reschedule can extract event UUID
        $eventUri = $response['resource']['event'] ?? null;

        Log::info('[Calendly] createBooking response', ['event' => $eventUri]);

        if ($eventUri) {
            return array_merge($response['resource'] ?? [], ['uid' => $eventUri]);
        }

        return $response['resource'] ?? [];
    }

    public function cancelBooking(string $bookingUid, ?string $reason = null): bool
    {
        $uuid = $this->extractUuid($bookingUid);

        $payload = [];

        if ($reason !== null && $reason !== '') {
            $payload['reason'] = $reason;
        }

        Log::info('[Calendly] cancelBooking', ['uuid' => $uuid]);

        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->post(self::BASE_URL . '/scheduled_events/' . $uuid . '/cancellation', $payload);

        $success = $response->successful();

        Log::info('[Calendly] cancelBooking response', ['uuid' => $uuid, 'status' => $response->status(), 'success' => $success]);

        return $success;
    }

    public function rescheduleBooking(string $bookingUid, string $newSlot): array
    {
        // Calendly has no reschedule endpoint — cancel original then re-book with same invitee
        $uuid = $this->extractUuid($bookingUid);

        Log::info('[Calendly] rescheduleBooking — fetching invitees', ['uuid' => $uuid]);

        $inviteesResponse = $this->get('/scheduled_events/' . $uuid . '/invitees');
        $invitees = $inviteesResponse['collection'] ?? [];

        if (empty($invitees)) {
            Log::error('[Calendly] rescheduleBooking — no invitees found', ['uuid' => $uuid]);

            return [];
        }

        $invitee = $invitees[0];
        $name = $invitee['name'] ?? '';
        $email = $invitee['email'] ?? '';
        $timezone = $invitee['timezone'] ?? 'UTC';

        Log::info('[Calendly] rescheduleBooking — cancelling original', ['uuid' => $uuid]);

        $this->cancelBooking($bookingUid, 'Rescheduled');

        return $this->createBooking($newSlot, $name, $email, $timezone);
    }

    private function get(string $path): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->get(self::BASE_URL . $path);

        if ($response->failed()) {
            Log::error('[Calendly] GET failed', ['path' => $path, 'status' => $response->status(), 'body' => $response->body()]);
        }

        return $response->json() ?? [];
    }

    private function post(string $path, array $payload): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->post(self::BASE_URL . $path, $payload);

        if ($response->failed()) {
            Log::error('[Calendly] POST failed', ['path' => $path, 'status' => $response->status(), 'body' => $response->body()]);
        }

        return $response->json() ?? [];
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ];
    }

    private function extractUuid(string $uri): string
    {
        // Accepts full URI (https://api.calendly.com/scheduled_events/UUID) or bare UUID
        return basename($uri);
    }
}
