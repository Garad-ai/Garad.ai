<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\Concerns\NormalizesCrmInput;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use Exception;

class AddNoteAction implements ActionInterface
{
    use NormalizesCrmInput;

    private const NOTE_TYPES = ['note', 'call', 'meeting', 'email'];

    /**
     * Config keys:
     *   - notable         (string, req) : contact | deal
     *   - notable_id      (int)         : ID of the target record; overrides record_name
     *   - record_name     (string)      : contact full name or deal title, usually an AI-extracted
     *                                     variable; contacts are created when new, deals must exist
     *   - contact_email   (string)      : dedup key when resolving/creating a contact by name
     *   - type            (string)      : note | call | meeting | email (default: note)
     *   - scheduled_at    (string)      : date/time for call and meeting notes; unparsable → null
     *   - content         (string, req)
     *   - store_output_as (string)      : context key for the result (default: created_note)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'created_note';
        $notable = (string) ($config['notable'] ?? '');
        $content = trim((string) ($config['content'] ?? ''));

        if ($content === '') {
            throw new Exception('add_note requires content.');
        }

        $target = match ($notable) {
            'contact' => $this->resolveContactTarget($config, $workflow),
            'deal'    => $this->resolveDealTarget($config, $workflow),
            default   => throw new Exception('add_note: notable must be "contact" or "deal".'),
        };

        $type = $this->nullableString($config['type'] ?? null);
        $type = in_array($type, self::NOTE_TYPES, true) ? $type : 'note';

        $note = $target->activityNotes()->create([
            'user_id'      => $workflow->user_id,
            'source_label' => $workflow->name,
            'type'         => $type,
            'content'      => $content,
            'scheduled_at' => in_array($type, ['call', 'meeting'], true)
                ? $this->parseDateTime($config['scheduled_at'] ?? null)
                : null,
        ]);

        $result = [
            'id'         => $note->id,
            'notable'    => $notable,
            'notable_id' => $target->id,
            'type'       => $note->type,
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveContactTarget(array $config, AIAgentWorkflow $workflow): CrmContact
    {
        $contactId = $this->nullableId($config['notable_id'] ?? null) ?? $this->resolveContactId([
            'contact_name'  => $config['record_name'] ?? null,
            'contact_email' => $config['contact_email'] ?? null,
        ], $workflow);

        if ($contactId === null) {
            throw new Exception('add_note requires a contact — pick one or pass a contact name in record_name.');
        }

        return CrmContact::query()
            ->where('id', $contactId)
            ->where('user_id', $workflow->user_id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveDealTarget(array $config, AIAgentWorkflow $workflow): CrmDeal
    {
        $dealId = $this->nullableId($config['notable_id'] ?? null) ?? $this->resolveDealId([
            'deal_title' => $config['record_name'] ?? null,
        ], $workflow);

        if ($dealId === null) {
            $dealTitle = $this->nullableString($config['record_name'] ?? null);

            throw new Exception($dealTitle !== null
                ? sprintf('add_note: no deal named "%s" was found.', $dealTitle)
                : 'add_note requires a deal — pick one or pass a deal title in record_name.');
        }

        return CrmDeal::query()
            ->where('id', $dealId)
            ->where('user_id', $workflow->user_id)
            ->firstOrFail();
    }
}
