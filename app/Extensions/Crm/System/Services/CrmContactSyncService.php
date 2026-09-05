<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Services;

use App\Extensions\Chatbot\System\Models\ChatbotCustomer;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroupSubscriber;
use App\Extensions\MarketingBot\System\Models\Whatsapp\ContactList;
use Illuminate\Database\Eloquent\Model;

class CrmContactSyncService
{
    /**
     * Source slug → backfill source model class.
     *
     * @var array<string, class-string<Model>>
     */
    private const SOURCE_MODEL = [
        'marketing_whatsapp' => ContactList::class,
        'marketing_telegram' => TelegramGroupSubscriber::class,
        'chatbot'            => ChatbotCustomer::class,
    ];

    /**
     * Per-source setting key that must be enabled (in addition to the master
     * switch) for a contact from that source to be synced.
     *
     * @var array<string, string>
     */
    private const SOURCE_SETTING = [
        'marketing_whatsapp' => 'crm_sync_marketing_whatsapp',
        'marketing_telegram' => 'crm_sync_marketing_telegram',
        'chatbot'            => 'crm_sync_chatbot',
    ];

    /**
     * Mirror a captured contact into crm_contacts.
     *
     * Idempotent: dedupes by email (then normalized phone) within the user's
     * contacts, updating only currently-empty fields. Returns null when the
     * sync is disabled or there is nothing meaningful to store.
     */
    public function sync(int $userId, string $name, ?string $email = null, ?string $phone = null, ?int $countryCode = null, string $source = 'unknown'): ?CrmContact
    {
        if (! $this->isEnabled($source)) {
            return null;
        }

        $name = trim($name);

        if ($name === '') {
            return null;
        }

        [$firstName, $lastName] = $this->splitName($name);

        $email = $email !== null ? trim($email) : '';
        $email = $email !== '' ? $email : null;

        $phone = $this->normalizePhone($phone ?? '', $countryCode);
        $phone = $phone !== '' ? $phone : null;

        $existing = $this->findExisting($userId, $email, $phone, $firstName, $lastName, $source);

        if ($existing !== null) {
            return $this->fillEmpty($existing, $firstName, $lastName, $email, $phone, $source);
        }

        return CrmContact::query()->create([
            'user_id'      => $userId,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'email'        => $email,
            'phone'        => $phone,
            'status'       => 'active',
            'source_label' => $source,
        ]);
    }

    /**
     * Resolve the backfill source model class, or null when the owning
     * extension is not installed.
     *
     * @return class-string<Model>|null
     */
    public function modelClassFor(string $source): ?string
    {
        $class = self::SOURCE_MODEL[$source] ?? null;

        return $class !== null && class_exists($class) ? $class : null;
    }

    /**
     * Map a source record to the normalized payload and sync it.
     * Returns true when a CRM contact was created or updated.
     */
    public function syncRecord(Model $record, string $source): bool
    {
        [$name, $email, $phone, $countryCode] = match ($source) {
            'marketing_whatsapp' => [
                trim($record->getAttribute('name') . ' ' . $record->getAttribute('last_name')),
                null,
                $record->getAttribute('phone'),
                $record->getAttribute('country_code') !== null ? (int) $record->getAttribute('country_code') : null,
            ],
            'marketing_telegram' => [
                (string) $record->getAttribute('name'),
                null,
                $record->getAttribute('phone'),
                null,
            ],
            'chatbot' => [
                (string) $record->getAttribute('name'),
                $record->getAttribute('email'),
                $record->getAttribute('phone'),
                $record->getAttribute('country_code') !== null ? (int) $record->getAttribute('country_code') : null,
            ],
            default => ['', null, null, null],
        };

        // Skip chatbot rows that never captured any contact detail.
        if ($source === 'chatbot' && ($email === null || $email === '') && ($phone === null || $phone === '')) {
            return false;
        }

        return $this->sync((int) $record->getAttribute('user_id'), $name, $email, $phone, $countryCode, $source) !== null;
    }

    public function isEnabled(string $source): bool
    {
        if (! (bool) setting('crm_contact_sync_enabled', '0')) {
            return false;
        }

        $perSource = self::SOURCE_SETTING[$source] ?? null;

        if ($perSource === null) {
            return false;
        }

        return (bool) setting($perSource, '0');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', $name) ?: [$name];
        $first = array_shift($parts);

        return [$first, implode(' ', $parts)];
    }

    private function findExisting(int $userId, ?string $email, ?string $phone, string $firstName, string $lastName, string $source): ?CrmContact
    {
        if ($email !== null) {
            $match = CrmContact::query()
                ->where('user_id', $userId)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();

            if ($match !== null) {
                return $match;
            }
        }

        if ($phone !== null) {
            $normalized = $this->digitsOnly($phone);

            if ($normalized !== '') {
                $match = CrmContact::query()
                    ->where('user_id', $userId)
                    ->whereNotNull('phone')
                    ->get()
                    ->first(fn (CrmContact $c) => $this->digitsOnly((string) $c->phone) === $normalized);

                if ($match !== null) {
                    return $match;
                }
            }
        }

        // Fallback for records with no email/phone (e.g. Telegram): dedupe by
        // same source + name so a re-run of the backfill does not duplicate.
        if ($email === null && $phone === null) {
            return CrmContact::query()
                ->where('user_id', $userId)
                ->where('source_label', $source)
                ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)])
                ->first();
        }

        return null;
    }

    private function fillEmpty(CrmContact $contact, string $firstName, string $lastName, ?string $email, ?string $phone, string $source): CrmContact
    {
        $updates = [];

        if (($contact->first_name === null || $contact->first_name === '') && $firstName !== '') {
            $updates['first_name'] = $firstName;
        }

        if (($contact->last_name === null || $contact->last_name === '') && $lastName !== '') {
            $updates['last_name'] = $lastName;
        }

        if (($contact->email === null || $contact->email === '') && $email !== null) {
            $updates['email'] = $email;
        }

        if (($contact->phone === null || $contact->phone === '') && $phone !== null) {
            $updates['phone'] = $phone;
        }

        if ($contact->source_label === null || $contact->source_label === '') {
            $updates['source_label'] = $source;
        }

        if ($updates !== []) {
            $contact->update($updates);
        }

        return $contact;
    }

    /**
     * Strip formatting while preserving a leading '+'; prefix the country code
     * when the number has no '+' and a code is supplied.
     */
    private function normalizePhone(string $phone, ?int $countryCode): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        $hasPlus = str_starts_with($phone, '+');
        $digits = $this->digitsOnly($phone);

        if ($digits === '') {
            return $phone;
        }

        if (! $hasPlus && $countryCode !== null && $countryCode > 0) {
            return '+' . $countryCode . $digits;
        }

        return ($hasPlus ? '+' : '') . $digits;
    }

    private function digitsOnly(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }
}
