<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Booking;

use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;
use Illuminate\Support\Str;

class BookingToolDefinitions
{
    /** @return array<int, array<string, mixed>> */
    public function forAnthropic(): array
    {
        return array_map(fn (array $def) => [
            'name'         => $def['name'],
            'description'  => $def['description'],
            'input_schema' => $def['parameters'],
        ], $this->definitions());
    }

    /** @return array<int, array<string, mixed>> */
    public function forOpenAi(): array
    {
        return array_map(fn (array $def) => [
            'type'     => 'function',
            'function' => [
                'name'        => $def['name'],
                'description' => $def['description'],
                'parameters'  => $def['parameters'],
            ],
        ], $this->definitions());
    }

    /** @return array<int, array<string, mixed>> */
    public function forGemini(): array
    {
        return $this->definitions();
    }

    /**
     * ElevenLabs standalone webhook tool configs (POST /v1/convai/tools) for an agent.
     *
     * The tool `name` is suffixed with the agent's slug + DB id to keep it unique across the
     * workspace-global tool namespace (e.g. create_booking_my_agent_name_1). The webhook URL keeps
     * the base action name so BookingToolHandler::handle() resolves it unchanged.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toolConfigsForElevenLabs(ExtPhoneCallAgent $agent): array
    {
        $suffix = Str::slug($agent->title, '_') . '_' . $agent->getKey();

        return array_map(fn (array $def) => [
            'type'        => 'webhook',
            'name'        => $def['name'] . '_' . $suffix,
            'description' => $def['description'],
            'api_schema'  => [
                'url' => route('api.phone-call-agent.webhook.elevenlabs.tool', [
                    'agentUuid' => $agent->uuid,
                    'toolName'  => $def['name'],
                ]),
                'method'              => 'POST',
                'request_body_schema' => $def['parameters'],
            ],
        ], $this->definitions());
    }

    /** @return array<int, string> */
    public function toolNames(): array
    {
        return array_map(fn (array $def) => $def['name'], $this->definitions());
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(): array
    {
        return [
            [
                'name'        => 'check_availability',
                'description' => 'Check available booking slots starting from a given date. Only call this after the caller has told you their preferred date. Returns a list of available time slots.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'date' => [
                            'type'        => 'string',
                            'description' => 'The date to check availability from, in YYYY-MM-DD format (e.g. 2026-06-03).',
                        ],
                    ],
                    'required' => ['date'],
                ],
            ],
            [
                'name'        => 'create_booking',
                'description' => 'Book an appointment for a caller. Requires the caller\'s name, email, chosen slot, and timezone. Always confirm details with the caller before calling this.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'slot' => [
                            'type'        => 'string',
                            'description' => 'The exact slot to book in ISO 8601 format (e.g. 2026-06-03T09:00:00Z).',
                        ],
                        'name' => [
                            'type'        => 'string',
                            'description' => 'Full name of the person being booked.',
                        ],
                        'email' => [
                            'type'        => 'string',
                            'description' => 'Email address of the person being booked.',
                        ],
                        'timezone' => [
                            'type'        => 'string',
                            'description' => 'IANA timezone of the person being booked (e.g. America/New_York).',
                        ],
                        'phone' => [
                            'type'        => 'string',
                            'description' => 'Phone number in E.164 format (optional).',
                        ],
                    ],
                    'required' => ['slot', 'name', 'email', 'timezone'],
                ],
            ],
            [
                'name'        => 'cancel_booking',
                'description' => 'Cancel an existing booking by its UID.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'booking_uid' => [
                            'type'        => 'string',
                            'description' => 'The unique identifier of the booking to cancel.',
                        ],
                        'reason' => [
                            'type'        => 'string',
                            'description' => 'Optional reason for cancellation.',
                        ],
                    ],
                    'required' => ['booking_uid'],
                ],
            ],
            [
                'name'        => 'reschedule_booking',
                'description' => 'Reschedule an existing booking to a new time slot.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'booking_uid' => [
                            'type'        => 'string',
                            'description' => 'The unique identifier of the booking to reschedule.',
                        ],
                        'new_slot' => [
                            'type'        => 'string',
                            'description' => 'The new slot in ISO 8601 format (e.g. 2026-06-05T14:00:00Z).',
                        ],
                    ],
                    'required' => ['booking_uid', 'new_slot'],
                ],
            ],
        ];
    }
}
