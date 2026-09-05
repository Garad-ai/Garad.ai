<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\Concerns\NormalizesCrmInput;
use App\Extensions\Crm\System\Models\CrmContact;
use Exception;

class CreateContactAction implements ActionInterface
{
    use NormalizesCrmInput;

    /**
     * Config keys:
     *   - first_name      (string, req)
     *   - last_name       (string)
     *   - email           (string)
     *   - phone           (string)
     *   - job_title       (string)
     *   - notes           (string)
     *   - company_id      (int)    : related CrmCompany ID
     *   - company_name    (string) : related company by name; matched or created when no company_id
     *   - store_output_as (string) : context key for the result (default: created_contact)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'created_contact';
        $firstName = $this->nullableString($config['first_name'] ?? null);

        if ($firstName === null) {
            throw new Exception('create_contact requires first_name.');
        }

        $contact = CrmContact::query()->create([
            'user_id'        => $workflow->user_id,
            'first_name'     => $firstName,
            'last_name'      => $this->nullableString($config['last_name'] ?? null) ?? '',
            'email'          => $this->nullableString($config['email'] ?? null),
            'phone'          => $this->nullableString($config['phone'] ?? null),
            'job_title'      => $this->nullableString($config['job_title'] ?? null),
            'notes'          => $this->nullableString($config['notes'] ?? null),
            'crm_company_id' => $this->resolveCompanyId($config, $workflow),
            'status'         => 'active',
        ]);

        $result = [
            'id'         => $contact->id,
            'name'       => trim($contact->first_name . ' ' . $contact->last_name),
            'email'      => $contact->email,
            'phone'      => $contact->phone,
            'status'     => $contact->status,
            'company_id' => $contact->crm_company_id,
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
