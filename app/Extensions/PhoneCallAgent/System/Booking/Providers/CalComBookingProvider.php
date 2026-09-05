<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Booking\Providers;

use App\Extensions\PhoneCallAgent\System\Booking\Contracts\BookingProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalComBookingProvider implements BookingProviderInterface
{
    private const BASE_URL = 'https://api.cal.com/v2';

    private const VERSION_SLOTS = '2024-09-04';

    private const VERSION_BOOKINGS = '2026-02-25';

    public function __construct(
        private readonly string $eventTypeId,
        private readonly string $apiKey,
    ) {}

    public function checkAvailability(string $startDate, string $endDate): array
    {
        $query = http_build_query([
            'eventTypeId' => $this->eventTypeId,
            'start'       => $startDate,
            'end'         => $endDate,
        ]);

        Log::info('[CalCom] checkAvailability', ['eventTypeId' => $this->eventTypeId, 'start' => $startDate, 'end' => $endDate]);

        $response = $this->get('/slots?' . $query, self::VERSION_SLOTS);

        $slots = $response['data'] ?? [];

        $flat = [];
        foreach ($slots as $dateSlots) {
            foreach ($dateSlots as $slot) {
                $flat[] = $slot['time'] ?? $slot['start'] ?? null;
            }
        }

        $result = array_values(array_filter($flat));

        Log::info('[CalCom] checkAvailability result', ['count' => count($result), 'slots' => $result]);

        return $result;
    }

    public function createBooking(string $slot, string $name, string $email, string $timezone, ?string $phone = null): array
    {
        $attendee = [
            'name'     => $name,
            'email'    => $email,
            'timeZone' => $timezone,
        ];

        if ($phone !== null && $phone !== '') {
            $attendee['phoneNumber'] = $phone;
        }

        $payload = [
            'eventTypeId' => (int) $this->eventTypeId,
            'start'       => $slot,
            'attendee'    => $attendee,
        ];

        Log::info('[CalCom] createBooking', ['eventTypeId' => $this->eventTypeId, 'slot' => $slot, 'email' => $email]);

        $response = $this->post('/bookings', $payload, self::VERSION_BOOKINGS);

        Log::info('[CalCom] createBooking response', ['status' => $response['status'] ?? null, 'uid' => $response['data']['uid'] ?? null]);

        return $response['data'] ?? $response;
    }

    public function cancelBooking(string $bookingUid, ?string $reason = null): bool
    {
        $payload = [];

        if ($reason !== null && $reason !== '') {
            $payload['cancellationReason'] = $reason;
        }

        Log::info('[CalCom] cancelBooking', ['bookingUid' => $bookingUid, 'reason' => $reason]);

        $response = $this->post('/bookings/' . $bookingUid . '/cancel', $payload, self::VERSION_BOOKINGS);

        $success = isset($response['status']) && $response['status'] === 'success';

        Log::info('[CalCom] cancelBooking response', ['bookingUid' => $bookingUid, 'success' => $success]);

        return $success;
    }

    public function rescheduleBooking(string $bookingUid, string $newSlot): array
    {
        Log::info('[CalCom] rescheduleBooking', ['bookingUid' => $bookingUid, 'newSlot' => $newSlot]);

        $response = $this->post('/bookings/' . $bookingUid . '/reschedule', ['start' => $newSlot], self::VERSION_BOOKINGS);

        Log::info('[CalCom] rescheduleBooking response', ['status' => $response['status'] ?? null, 'uid' => $response['data']['uid'] ?? null]);

        return $response['data'] ?? $response;
    }

    private function get(string $path, string $apiVersion): array
    {
        $response = Http::withHeaders($this->headers($apiVersion))
            ->timeout(15)
            ->get(self::BASE_URL . $path);

        if ($response->failed()) {
            Log::error('[CalCom] GET failed', ['path' => $path, 'status' => $response->status(), 'body' => $response->body()]);
        }

        return $response->json() ?? [];
    }

    private function post(string $path, array $payload, string $apiVersion): array
    {
        $response = Http::withHeaders($this->headers($apiVersion))
            ->timeout(15)
            ->post(self::BASE_URL . $path, $payload);

        if ($response->failed()) {
            Log::error('[CalCom] POST failed', ['path' => $path, 'status' => $response->status(), 'body' => $response->body()]);
        }

        return $response->json() ?? [];
    }

    /** @return array<string, string> */
    private function headers(string $apiVersion): array
    {
        return [
            'Authorization'   => 'Bearer ' . $this->apiKey,
            'cal-api-version' => $apiVersion,
        ];
    }
}
