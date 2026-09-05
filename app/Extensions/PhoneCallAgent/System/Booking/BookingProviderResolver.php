<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Booking;

use App\Extensions\PhoneCallAgent\System\Booking\Contracts\BookingProviderInterface;
use App\Extensions\PhoneCallAgent\System\Booking\Providers\CalComBookingProvider;
use App\Extensions\PhoneCallAgent\System\Booking\Providers\CalendlyBookingProvider;
use App\Extensions\PhoneCallAgent\System\Enums\BookingProviderEnum;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;

class BookingProviderResolver
{
    public function resolve(ExtPhoneCallAgent $agent): BookingProviderInterface
    {
        return match ($agent->booking_provider) {
            BookingProviderEnum::CalCom   => new CalComBookingProvider((string) $agent->booking_event_type_id, (string) $agent->booking_api_key),
            BookingProviderEnum::Calendly => new CalendlyBookingProvider((string) $agent->booking_event_type_id, (string) $agent->booking_api_key),
            default                       => throw new \RuntimeException('No booking provider configured.'),
        };
    }
}
