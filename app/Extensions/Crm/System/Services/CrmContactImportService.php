<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Services;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use Illuminate\Http\UploadedFile;

class CrmContactImportService
{
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
     * Parse and validate a CRM contacts CSV.
     *
     * @param  array{first_name?: string, last_name?: string, email?: string, phone?: string, job_title?: string, company?: string, status?: string}  $mapping  Maps app fields to CSV column names. Defaults to the literal field name when empty.
     * @param  string  $defaultStatus  fallback status ('active'|'inactive') when the status column is unmapped or the row value is unrecognized
     *
     * @return array{valid: list<array{first_name: string, last_name: string, email: ?string, phone: ?string, job_title: ?string, crm_company_id: ?int, company: string, status: string}>, invalid: list<array{row: array<string, string>, line: int, reason: string}>, error?: string}
     */
    public function parseContacts(UploadedFile $file, int $userId, array $mapping = [], string $defaultStatus = 'active'): array
    {
        $firstNameCol = strtolower($mapping['first_name'] ?? 'first_name');
        $lastNameCol = strtolower($mapping['last_name'] ?? 'last_name');
        $emailCol = strtolower($mapping['email'] ?? 'email');
        $phoneCol = strtolower($mapping['phone'] ?? 'phone');
        $jobTitleCol = strtolower($mapping['job_title'] ?? 'job_title');
        $companyCol = strtolower($mapping['company'] ?? 'company');
        $statusCol = strtolower($mapping['status'] ?? 'status');

        $defaultStatus = in_array(strtolower($defaultStatus), ['active', 'inactive'], true)
            ? strtolower($defaultStatus)
            : 'active';

        $rows = $this->readCsv($file);

        if ($rows === null) {
            return ['valid' => [], 'invalid' => [], 'error' => 'Could not read CSV file.'];
        }

        $valid = [];
        $invalid = [];

        $existingEmails = CrmContact::query()
            ->where('user_id', $userId)
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->flip()
            ->all();

        $companyMap = [];
        foreach (CrmCompany::query()->where('user_id', $userId)->pluck('id', 'name') as $name => $id) {
            $companyMap[strtolower(trim((string) $name))] = (int) $id;
        }

        $seenEmails = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // 1-based + header row

            $firstName = isset($row[$firstNameCol]) ? trim((string) $row[$firstNameCol]) : '';
            $lastName = isset($row[$lastNameCol]) ? trim((string) $row[$lastNameCol]) : '';
            $email = isset($row[$emailCol]) ? trim((string) $row[$emailCol]) : '';
            $phone = $this->parsePhone(isset($row[$phoneCol]) ? (string) $row[$phoneCol] : '');
            $jobTitle = isset($row[$jobTitleCol]) ? trim((string) $row[$jobTitleCol]) : '';
            $company = isset($row[$companyCol]) ? trim((string) $row[$companyCol]) : '';

            if ($firstName === '') {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'First name is required.'];

                continue;
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Invalid email format.'];

                continue;
            }

            if ($email !== '') {
                $normalizedEmail = strtolower($email);

                if (isset($existingEmails[$normalizedEmail])) {
                    $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Duplicate: already exists in your contacts.'];

                    continue;
                }

                if (isset($seenEmails[$normalizedEmail])) {
                    $invalid[] = ['row' => $row, 'line' => $lineNumber, 'reason' => 'Duplicate: appears more than once in this file.'];

                    continue;
                }

                $seenEmails[$normalizedEmail] = true;
            }

            $companyId = $company !== '' ? ($companyMap[strtolower($company)] ?? null) : null;

            $statusValue = isset($row[$statusCol]) ? strtolower(trim((string) $row[$statusCol])) : '';
            $status = in_array($statusValue, ['active', 'inactive'], true) ? $statusValue : $defaultStatus;

            $valid[] = [
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'email'          => $email !== '' ? $email : null,
                'phone'          => $phone !== '' ? $phone : null,
                'job_title'      => $jobTitle !== '' ? $jobTitle : null,
                'crm_company_id' => $companyId,
                'company'        => $company,
                'status'         => $status,
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
     */
    private function parsePhone(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        $prefix = str_starts_with($phone, '+') ? '+' : '';
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return $phone;
        }

        return $prefix . $digits;
    }
}
