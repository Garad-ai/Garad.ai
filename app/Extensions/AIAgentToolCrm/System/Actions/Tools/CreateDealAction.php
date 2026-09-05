<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\Concerns\NormalizesCrmInput;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStage;
use Exception;

class CreateDealAction implements ActionInterface
{
    use NormalizesCrmInput;

    /**
     * Config keys:
     *   - title               (string, req)
     *   - value               (float)
     *   - currency            (string)
     *   - stage_id            (int)    : defaults to the user's first stage
     *   - contact_id          (int)
     *   - contact_name        (string) : related contact by name; matched or created when no contact_id
     *   - contact_email       (string) : preferred match key for contact_name
     *   - company_id          (int)
     *   - company_name        (string) : related company by name; matched or created when no company_id
     *   - expected_close_date (string) : YYYY-MM-DD
     *   - description         (string)
     *   - store_output_as     (string) : context key for the result (default: created_deal)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'created_deal';
        $title = $this->nullableString($config['title'] ?? null);

        if ($title === null) {
            throw new Exception('create_deal requires title.');
        }

        CrmDealStage::createDefaultsForUser((int) $workflow->user_id);

        $stageId = $this->nullableId($config['stage_id'] ?? null);

        if ($stageId === null) {
            $stageId = CrmDealStage::query()
                ->where('user_id', $workflow->user_id)
                ->orderBy('order')
                ->value('id');
        }

        $companyId = $this->resolveCompanyId($config, $workflow);

        $deal = new CrmDeal([
            'user_id'             => $workflow->user_id,
            'title'               => $title,
            'value'               => $this->parseValue($config['value'] ?? null),
            'currency'            => $this->nullableString($config['currency'] ?? null) ?? 'USD',
            'crm_deal_stage_id'   => $stageId,
            'crm_contact_id'      => $this->resolveContactId($config, $workflow, $companyId),
            'crm_company_id'      => $companyId,
            'expected_close_date' => $this->parseDate($config['expected_close_date'] ?? null),
            'description'         => $this->nullableString($config['description'] ?? null),
        ]);
        $deal->stageChangeSourceLabel = $workflow->name;
        $deal->save();

        $deal->load('stage:id,name');

        $result = [
            'id'       => $deal->id,
            'title'    => $deal->title,
            'value'    => $deal->value,
            'currency' => $deal->currency,
            'stage'    => $deal->stage?->name,
            'stage_id' => $deal->crm_deal_stage_id,
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }

    /**
     * Deal values often arrive from an AI step as free text ("$50,000", "50 000 USD"), where a
     * plain float cast silently yields 0 or 50. Strip formatting before casting.
     */
    private function parseValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^\d.]/', '', str_replace(',', '', (string) $value)) ?? '';

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
