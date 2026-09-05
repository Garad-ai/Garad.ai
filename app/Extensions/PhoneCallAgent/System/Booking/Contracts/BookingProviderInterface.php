<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Booking\Contracts;

interface BookingProviderInterface
{
    public function checkAvailability(string $startDate, string $endDate): array;

    public function createBooking(string $slot, string $name, string $email, string $timezone, ?string $phone = null): array;

    public function cancelBooking(string $bookingUid, ?string $reason = null): bool;

    public function rescheduleBooking(string $bookingUid, string $newSlot): array;
}
