<?php

declare(strict_types=1);

namespace App\Extensions\MarketingBot\System\Services;

use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroupSubscriber;
use App\Extensions\MarketingBot\System\Models\Whatsapp\ContactList;
use Illuminate\Http\UploadedFile;

class CsvImportService
{
    private const PHONE_REGEX = '/^\+?[1-9]\d{7,14}$/';

    /**
     * Read only the header row of a CSV file.
     *
     * Returns the original (non-lowercased) header strings so the UI can
     * display them as-is for the column-mapping step.
     *
     * @return list<string>|null
     */
    public function readCsvHeaders(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return null;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return null;
        }

        $headers = fgetcsv($handle);
        fclose($handle);

        if ($headers === false || $headers === null) {
            return null;
        }

        return array_values(
            array_filter(
                array_map(fn ($h) => trim((string) $h), $headers),
                fn ($h) => $h !== '',
            )
        );
    }

    /**
     * Parse and validate a WhatsApp contacts CSV.
     *
     * @param  array{name?: string, phone?: string, country_code?: string}  $mapping  Maps app fields to CSV column names.
     *                                                                                Defaults to the literal field name when empty.
     *
     * @return array{valid: list<array{name: string, phone: string, country_code: ?int}>, invalid: list<array{row: array<string, string>, reason: string}>}
     */
    public function parseWhatsapp(UploadedFile $file, int $userId, array $mapping = []): array
    {
        $nameCol = strtolower($mapping['name'] ?? 'name');
        $phoneCol = strtolower($mapping['phone'] ?? 'phone');
        $ccCol = strtolower($mapping['country_code'] ?? 'country_code');

        $rows = $this->readCsv($file);

        if ($rows === null) {
            return ['valid' => [], 'invalid' => [], 'error' => 'Could not read CSV file.'];
        }

        $valid = [];
        $invalid = [];

        $existingPhones = ContactList::query()
            ->where('user_id', $userId)
            ->pluck('phone')
            ->map(fn ($p) => $this->normalizePhone($p))
            ->flip()
            ->all();

        $seenPhones = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // 1-based + header row

            $name = isset($row[$nameCol]) ? trim((string) $row[$nameCol]) : '';
            $phone = $this->parsePhone(isset($row[$phoneCol]) ? (string) $row[$phoneCol] : '');

            if ($name === '') {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Name is required.'];

                continue;
            }

            if ($phone === '') {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Phone is required.'];

                continue;
            }

            if (! preg_match(self::PHONE_REGEX, $phone)) {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Invalid phone number format.'];

                continue;
            }

            $normalized = $this->normalizePhone($phone);

            if (isset($existingPhones[$normalized])) {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Duplicate: already exists in your contacts.'];

                continue;
            }

            if (isset($seenPhones[$normalized])) {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Duplicate: appears more than once in this file.'];

                continue;
            }

            $seenPhones[$normalized] = true;

            $countryCode = $ccCol !== '' && isset($row[$ccCol]) && $row[$ccCol] !== ''
                ? (int) $row[$ccCol]
                : null;

            $valid[] = [
                'name'         => $name,
                'phone'        => $phone,
                'country_code' => $countryCode,
            ];
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * Parse and validate a Telegram contacts CSV.
     *
     * @param  array{name?: string, username?: string, phone?: string}  $mapping  Maps app fields to CSV column names.
     *                                                                            Defaults to the literal field name when empty.
     *
     * @return array{valid: list<array{name: string, username: string, phone: ?string}>, invalid: list<array{row: array<string, string>, reason: string}>}
     */
    public function parseTelegram(UploadedFile $file, int $userId, array $mapping = []): array
    {
        $nameCol = strtolower($mapping['name'] ?? 'name');
        $usernameCol = strtolower($mapping['username'] ?? 'username');
        $phoneCol = strtolower($mapping['phone'] ?? 'phone');

        $rows = $this->readCsv($file);

        if ($rows === null) {
            return ['valid' => [], 'invalid' => [], 'error' => 'Could not read CSV file.'];
        }

        $valid = [];
        $invalid = [];

        $existingUsernames = TelegramGroupSubscriber::query()
            ->where('user_id', $userId)
            ->whereNotNull('username')
            ->pluck('username')
            ->map(fn ($u) => strtolower(ltrim((string) $u, '@')))
            ->flip()
            ->all();

        $seenUsernames = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;

            $name = isset($row[$nameCol]) ? trim((string) $row[$nameCol]) : '';
            $username = isset($row[$usernameCol]) ? trim((string) $row[$usernameCol]) : '';
            $phone = $this->parsePhone(isset($row[$phoneCol]) ? (string) $row[$phoneCol] : '');

            if ($name === '' && $username === '') {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Either name or username is required.'];

                continue;
            }

            if ($phone !== '' && ! preg_match(self::PHONE_REGEX, $phone)) {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Invalid phone number format.'];

                continue;
            }

            $normalizedUsername = strtolower(ltrim($username, '@'));

            if ($normalizedUsername !== '' && isset($existingUsernames[$normalizedUsername])) {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Duplicate: username already exists in your contacts.'];

                continue;
            }

            if ($normalizedUsername !== '' && isset($seenUsernames[$normalizedUsername])) {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Duplicate: username appears more than once in this file.'];

                continue;
            }

            if ($normalizedUsername !== '') {
                $seenUsernames[$normalizedUsername] = true;
            }

            $valid[] = [
                'name'     => $name !== '' ? $name : $username,
                'username' => $username,
                'phone'    => $phone !== '' ? $phone : null,
            ];
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * Read CSV into an array of associative rows keyed by header columns.
     *
     * @return list<array<string, string>>|null
     */
    private function readCsv(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            return null;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return null;
        }

        $headers = fgetcsv($handle);

        if ($headers === false || $headers === null) {
            fclose($handle);

            return null;
        }

        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null]) {
                continue;
            }

            $row = [];

            foreach ($headers as $i => $header) {
                $row[$header] = isset($line[$i]) ? (string) $line[$i] : '';
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Strip common phone formatting characters (spaces, dashes, dots, parentheses)
     * while preserving a leading '+' for E.164 format.
     *
     * Examples:
     *   "+90-312-800-8008" → "+903128008008"
     *   "(555) 123.4567"   → "5551234567"
     *   "notaphone"        → "notaphone"  (no digits → return original so regex rejects it)
     *   ""                 → ""
     */
    private function parsePhone(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        $prefix = str_starts_with($phone, '+') ? '+' : '';
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        // If stripping produced no digits, the value is non-numeric garbage;
        // return original so the E.164 regex rejects it with a clear error.
        if ($digits === '') {
            return $phone;
        }

        return $prefix . $digits;
    }

    /**
     * Strip ALL non-digits for duplicate-detection comparison only.
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? $phone;
    }
}
