<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools\Concerns;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStage;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Step config comes from the builder's form controls, which submit "" for an untouched
 * relation dropdown or date picker. Casting those straight to int/string wrote 0 into a
 * foreign key and "" into a datetime column, so the whole run failed on the insert.
 */
trait NormalizesCrmInput
{
    /**
     * An AI extraction step is told to emit null for absent data, but models routinely answer
     * with a placeholder word instead. Persisting those writes "N/A" into a phone column.
     *
     * @var array<int, string>
     */
    private array $placeholderValues = [
        'n/a', 'na', 'none', 'null', 'nil', 'unknown', 'unspecified',
        'not provided', 'not specified', 'not available', '-', '--',
    ];

    protected function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '' || in_array(mb_strtolower($string), $this->placeholderValues, true)) {
            return null;
        }

        return $string;
    }

    /**
     * A model asked for YYYY-MM-DD still answers "next Friday" or "Q3 2026" often enough that an
     * unparsable string must not reach the date column and fail the whole run.
     */
    protected function parseDate(mixed $value): ?string
    {
        $date = $this->nullableString($value);

        if ($date === null) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Datetime variant for columns like crm_notes.scheduled_at, where "tomorrow 2pm" from a
     * model must either become a real timestamp or stay null.
     */
    protected function parseDateTime(mixed $value): ?string
    {
        $raw = $this->nullableString($value);

        if ($raw === null) {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * An AI extraction step can only produce a company name, never an internal ID, so the name
     * is matched against the user's existing companies and created when it is new.
     *
     * @param  array<string, mixed>  $config
     */
    protected function resolveCompanyId(array $config, AIAgentWorkflow $workflow): ?int
    {
        $companyId = $this->nullableId($config['company_id'] ?? null);

        if ($companyId !== null) {
            return $companyId;
        }

        $companyName = $this->nullableString($config['company_name'] ?? null);

        if ($companyName === null) {
            return null;
        }

        $company = CrmCompany::query()
            ->where('user_id', $workflow->user_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($companyName)])
            ->first();

        $company ??= CrmCompany::query()->create([
            'user_id' => $workflow->user_id,
            'name'    => $companyName,
        ]);

        return $company->id;
    }

    /**
     * Same idea for people: match on email first because it is unique in practice, then on the
     * full name, and only create when neither finds an existing contact.
     *
     * @param  array<string, mixed>  $config
     */
    protected function resolveContactId(array $config, AIAgentWorkflow $workflow, ?int $companyId = null): ?int
    {
        $contactId = $this->nullableId($config['contact_id'] ?? null);

        if ($contactId !== null) {
            return $contactId;
        }

        $email = $this->nullableString($config['contact_email'] ?? null);
        $name = $this->nullableString($config['contact_name'] ?? null);

        if ($email === null && $name === null) {
            return null;
        }

        $query = CrmContact::query()->where('user_id', $workflow->user_id);

        $existing = $email !== null
            ? (clone $query)->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first()
            : null;

        if ($existing === null && $name !== null) {
            $existing = (clone $query)
                ->whereRaw("LOWER(TRIM(CONCAT(first_name, ' ', last_name))) = ?", [mb_strtolower($name)])
                ->first();
        }

        if ($existing !== null) {
            return $existing->id;
        }

        if ($name === null) {
            return null;
        }

        [$firstName, $lastName] = $this->splitName($name);

        return CrmContact::query()->create([
            'user_id'        => $workflow->user_id,
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'email'          => $email,
            'crm_company_id' => $companyId,
            'status'         => 'active',
        ])->id;
    }

    /**
     * Deals are matched by title only — silently creating a deal from a note reference would
     * invent pipeline records, so an unknown title resolves to null and the caller decides.
     *
     * @param  array<string, mixed>  $config
     */
    protected function resolveDealId(array $config, AIAgentWorkflow $workflow): ?int
    {
        $dealId = $this->nullableId($config['deal_id'] ?? null);

        if ($dealId !== null) {
            return $dealId;
        }

        $dealTitle = $this->nullableString($config['deal_title'] ?? null);

        if ($dealTitle === null) {
            return null;
        }

        return CrmDeal::query()
            ->where('user_id', $workflow->user_id)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($dealTitle)])
            ->value('id');
    }

    /**
     * Pipeline stages are user-defined structure, so a name only ever matches an existing
     * stage — creating one on the fly would silently reshape the pipeline.
     *
     * @param  array<string, mixed>  $config
     */
    protected function resolveStageId(array $config, AIAgentWorkflow $workflow): ?int
    {
        $stageId = $this->nullableId($config['stage_id'] ?? null);

        if ($stageId !== null) {
            return $stageId;
        }

        $stageName = $this->nullableString($config['stage_name'] ?? null);

        if ($stageName === null) {
            return null;
        }

        return CrmDealStage::query()
            ->where('user_id', $workflow->user_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($stageName)])
            ->value('id');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', $name, 2) ?: [$name];

        return [$parts[0], $parts[1] ?? ''];
    }
}
