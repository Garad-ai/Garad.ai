<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Booking;

use App\Extensions\PhoneCallAgent\System\Booking\Contracts\BookingProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookingToolHandler
{
    public function __construct(private readonly BookingProviderInterface $provider) {}

    public function handle(string $toolName, array $input): string
    {
        Log::info('[BookingTool] handle', ['tool' => $toolName, 'input' => $input]);

        try {
            $result = match ($toolName) {
                'check_availability' => $this->handleCheckAvailability($input),
                'create_booking'     => $this->handleCreateBooking($input),
                'cancel_booking'     => $this->handleCancelBooking($input),
                'reschedule_booking' => $this->handleReschedule($input),
                default              => 'Unknown booking tool: ' . $toolName,
            };

            Log::info('[BookingTool] result', ['tool' => $toolName, 'result' => $result]);

            return $result;
        } catch (Throwable $e) {
            Log::error('[BookingTool] exception', ['tool' => $toolName, 'error' => $e->getMessage()]);

            return 'Booking action failed: ' . $e->getMessage();
        }
    }

    private function handleCheckAvailability(array $input): string
    {
        $date = $input['date'] ?? '';

        if ($date === '') {
            return 'Please provide a date to check availability.';
        }

        $parsed = Carbon::parse($date);
        $start = $parsed->isToday() ? Carbon::now()->toIso8601String() : $parsed->startOfDay()->toIso8601String();
        $end = Carbon::parse($date)->addDays(6)->endOfDay()->toIso8601String();

        $slots = $this->provider->checkAvailability($start, $end);

        if (empty($slots)) {
            return 'No available slots found for the week starting ' . $date . '.';
        }

        return 'Available slots: ' . implode(', ', $slots);
    }

    private function handleCreateBooking(array $input): string
    {
        $slot = $input['slot'] ?? '';
        $name = $input['name'] ?? '';
        $email = $input['email'] ?? '';

        if ($slot === '' || $email === '' || $name === '') {
            $missing = implode(', ', array_filter([
                $slot === '' ? 'slot' : null,
                $name === '' ? 'name' : null,
                $email === '' ? 'email' : null,
            ]));

            return "Cannot create booking. Missing required fields: {$missing}. Please collect these from the caller first.";
        }

        $data = $this->provider->createBooking(
            slot: $slot,
            name: $name,
            email: $email,
            timezone: $input['timezone'] ?? 'UTC',
            phone: $input['phone'] ?? null,
        );

        $uid = $data['uid'] ?? $data['id'] ?? null;

        if ($uid) {
            return "Booking confirmed. UID: {$uid}. A confirmation has been sent to {$email}.";
        }

        return 'Booking failed. Please verify the slot is still available and try again.';
    }

    private function handleCancelBooking(array $input): string
    {
        $success = $this->provider->cancelBooking(
            bookingUid: $input['booking_uid'] ?? '',
            reason: $input['reason'] ?? null,
        );

        return $success
            ? 'Booking has been cancelled successfully.'
            : 'Failed to cancel the booking. Please verify the booking UID and try again.';
    }

    private function handleReschedule(array $input): string
    {
        $data = $this->provider->rescheduleBooking(
            bookingUid: $input['booking_uid'] ?? '',
            newSlot: $input['new_slot'] ?? '',
        );

        $uid = $data['uid'] ?? $data['id'] ?? null;

        if ($uid) {
            return "Booking rescheduled successfully. New booking UID: {$uid}.";
        }

        return 'Booking has been rescheduled successfully.';
    }
}
