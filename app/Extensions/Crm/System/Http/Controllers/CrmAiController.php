<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmPresentation;
use App\Extensions\Crm\System\Models\CrmProject;
use App\Extensions\Crm\System\Models\CrmTask;
use App\Extensions\Crm\System\Models\SalesInvoice;
use App\Extensions\Crm\System\Models\SalesInvoiceItem;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

/**
 * Executes the CRM actions proposed by the assistant through the `<<<ACTIONS>>>`
 * protocol. Chat rendering, history and streaming live in
 * CrmAssistantChatController and the core chat engine.
 */
class CrmAiController extends Controller
{
    public function executeActions(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $request->validate([
            'actions'          => 'required|array|min:1|max:10',
            'actions.*.action' => 'required|in:create,update,delete',
            'actions.*.entity' => 'required|in:contact,company,deal,task,project,invoice,presentation',
            'actions.*.id'     => 'nullable|integer',
            'actions.*.data'   => 'nullable|array',
        ]);

        $userId = Auth::id();
        $results = [];

        DB::beginTransaction();

        try {
            foreach ($request->input('actions') as $actionItem) {
                $results[] = $this->executeSingleAction($actionItem, $userId);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: __('Failed to execute actions. All changes have been rolled back.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Actions executed successfully.'),
            'results' => $results,
        ]);
    }

    private function executeSingleAction(array $actionItem, int $userId): array
    {
        $action = $actionItem['action'];
        $entity = $actionItem['entity'];
        $id = $actionItem['id'] ?? null;
        $data = $actionItem['data'] ?? [];
        $modelClass = $this->getModelClass($entity);

        // Invoice entity has special handling for line items + auto-generated number
        if ($entity === 'invoice') {
            return $this->executeInvoiceAction($action, $id, $data, $userId);
        }

        // Presentation entity delegates to CrmPresentationController
        if ($entity === 'presentation') {
            return $this->executePresentationAction($action, $id, $data, $userId);
        }

        switch ($action) {
            case 'create':
                $rules = $this->getValidationRules($entity, 'create', $userId);
                $validated = validator($data, $rules)->validate();
                $record = $modelClass::create(array_merge($validated, ['user_id' => $userId]));

                return ['action' => 'create', 'entity' => $entity, 'id' => $record->id, 'success' => true];

            case 'update':
                $record = $modelClass::where('id', $id)->where('user_id', $userId)->firstOrFail();
                $rules = $this->getValidationRules($entity, 'update', $userId);
                $validated = validator($data, $rules)->validate();

                if (in_array($entity, ['task', 'project']) && array_key_exists('status', $validated)) {
                    $validated['completed_at'] = $validated['status'] === 'completed' ? now() : null;
                }

                $record->update($validated);

                return ['action' => 'update', 'entity' => $entity, 'id' => $id, 'success' => true];

            case 'delete':
                $record = $modelClass::where('id', $id)->where('user_id', $userId)->firstOrFail();
                $record->delete();

                return ['action' => 'delete', 'entity' => $entity, 'id' => $id, 'success' => true];

            default:
                throw new InvalidArgumentException("Unknown action: {$action}");
        }
    }

    private function executeInvoiceAction(string $action, ?int $id, array $data, int $userId): array
    {
        switch ($action) {
            case 'create':
                $rules = $this->getValidationRules('invoice', 'create', $userId);
                $validated = validator($data, $rules)->validate();

                // Extract items before creating the invoice
                $items = $validated['items'] ?? [];
                unset($validated['items']);

                // Auto-generate invoice number and set defaults
                $validated['invoice_number'] = SalesInvoice::generateNumber($userId);
                $validated['status'] = $validated['status'] ?? 'draft';
                $validated['issue_date'] = $validated['issue_date'] ?? now()->format('Y-m-d');
                $validated['due_date'] = $validated['due_date'] ?? now()->addDays(30)->format('Y-m-d');
                $validated['currency'] = $validated['currency'] ?? 'USD';
                $validated['subtotal'] = 0;
                $validated['tax_amount'] = 0;
                $validated['total'] = 0;
                $validated['amount_paid'] = 0;

                $invoice = SalesInvoice::create(array_merge($validated, ['user_id' => $userId]));

                // Create line items
                foreach ($items as $index => $item) {
                    $itemTotal = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
                    SalesInvoiceItem::create([
                        'sales_invoice_id' => $invoice->id,
                        'description'      => $item['description'],
                        'quantity'         => $item['quantity'],
                        'unit_price'       => $item['unit_price'],
                        'unit'             => $item['unit'] ?? null,
                        'total'            => $itemTotal,
                        'sort_order'       => $index,
                    ]);
                }

                // Recalculate totals (subtotal, discount, tax, total)
                $invoice->recalculateTotals();

                return ['action' => 'create', 'entity' => 'invoice', 'id' => $invoice->id, 'success' => true];

            case 'update':
                $invoice = SalesInvoice::where('id', $id)->where('user_id', $userId)->firstOrFail();
                $rules = $this->getValidationRules('invoice', 'update', $userId);
                $validated = validator($data, $rules)->validate();

                // Extract items if provided
                $items = $validated['items'] ?? null;
                unset($validated['items']);

                // Update invoice fields
                if (! empty($validated)) {
                    $invoice->update($validated);
                }

                // Replace line items if provided
                if ($items !== null) {
                    $invoice->items()->delete();

                    foreach ($items as $index => $item) {
                        $itemTotal = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
                        SalesInvoiceItem::create([
                            'sales_invoice_id' => $invoice->id,
                            'description'      => $item['description'],
                            'quantity'         => $item['quantity'],
                            'unit_price'       => $item['unit_price'],
                            'unit'             => $item['unit'] ?? null,
                            'total'            => $itemTotal,
                            'sort_order'       => $index,
                        ]);
                    }

                    $invoice->recalculateTotals();
                }

                return ['action' => 'update', 'entity' => 'invoice', 'id' => $id, 'success' => true];

            case 'delete':
                $invoice = SalesInvoice::where('id', $id)->where('user_id', $userId)->firstOrFail();
                $invoice->items()->delete();
                $invoice->delete();

                return ['action' => 'delete', 'entity' => 'invoice', 'id' => $id, 'success' => true];

            default:
                throw new InvalidArgumentException("Unknown action: {$action}");
        }
    }

    private function executePresentationAction(string $action, ?int $id, array $data, int $userId): array
    {
        switch ($action) {
            case 'create':
                $rules = $this->getValidationRules('presentation', 'create', $userId);
                $validated = validator($data, $rules)->validate();

                // Delegate to the presentation controller's store logic
                $request = new Request;
                $request->merge([
                    'crm_deal_id' => $validated['crm_deal_id'],
                    'style'       => $validated['style'] ?? 'corporate',
                ]);
                $request->setUserResolver(fn () => Auth::user());

                $controller = app(CrmPresentationController::class);
                $response = $controller->store($request);
                $responseData = $response->getData(true);

                if (! ($responseData['success'] ?? false)) {
                    throw new RuntimeException($responseData['message'] ?? __('Failed to generate presentation.'));
                }

                return [
                    'action'  => 'create',
                    'entity'  => 'presentation',
                    'id'      => $responseData['presentation_id'] ?? null,
                    'success' => true,
                ];

            case 'delete':
                $presentation = CrmPresentation::where('id', $id)->where('user_id', $userId)->firstOrFail();
                $presentation->delete();

                return ['action' => 'delete', 'entity' => 'presentation', 'id' => $id, 'success' => true];

            default:
                throw new InvalidArgumentException(__('Presentations can only be created or deleted.'));
        }
    }

    private function getModelClass(string $entity): string
    {
        return match ($entity) {
            'contact'      => CrmContact::class,
            'company'      => CrmCompany::class,
            'deal'         => CrmDeal::class,
            'task'         => CrmTask::class,
            'project'      => CrmProject::class,
            'invoice'      => SalesInvoice::class,
            'presentation' => CrmPresentation::class,
            default        => throw new InvalidArgumentException("Unknown entity: {$entity}"),
        };
    }

    private function getValidationRules(string $entity, string $action, int $userId): array
    {
        $req = $action === 'create' ? 'required' : 'sometimes|required';

        return match ($entity) {
            'contact' => [
                'first_name'     => "{$req}|string|max:255",
                'last_name'      => "{$req}|string|max:255",
                'email'          => 'nullable|email|max:255',
                'phone'          => 'nullable|string|max:255',
                'job_title'      => 'nullable|string|max:255',
                'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', $userId)],
                'status'         => 'nullable|in:active,inactive',
                'notes'          => 'nullable|string|max:5000',
            ],
            'company' => [
                'name'     => "{$req}|string|max:255",
                'industry' => 'nullable|string|max:255',
                'website'  => 'nullable|string|max:255',
                'phone'    => 'nullable|string|max:255',
                'email'    => 'nullable|email|max:255',
                'address'  => 'nullable|string|max:1000',
                'city'     => 'nullable|string|max:255',
                'state'    => 'nullable|string|max:255',
                'country'  => 'nullable|string|max:255',
                'notes'    => 'nullable|string|max:5000',
            ],
            'deal' => [
                'title'               => "{$req}|string|max:255",
                'crm_deal_stage_id'   => ["{$req}", Rule::exists('crm_deal_stages', 'id')->where('user_id', $userId)],
                'crm_contact_id'      => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', $userId)],
                'crm_company_id'      => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', $userId)],
                'value'               => 'nullable|numeric|min:0',
                'currency'            => 'nullable|string|max:10',
                'expected_close_date' => 'nullable|date',
                'description'         => 'nullable|string|max:5000',
            ],
            'task' => [
                'title'          => "{$req}|string|max:255",
                'description'    => 'nullable|string|max:5000',
                'type'           => 'nullable|in:task,call,meeting,email,follow_up',
                'status'         => 'nullable|in:pending,completed,cancelled',
                'priority'       => 'nullable|in:low,medium,high',
                'due_date'       => 'nullable|date',
                'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', $userId)],
                'crm_deal_id'    => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', $userId)],
                'crm_project_id' => ['nullable', Rule::exists('crm_projects', 'id')->where('user_id', $userId)],
            ],
            'project' => [
                'name'           => "{$req}|string|max:255",
                'description'    => 'nullable|string|max:5000',
                'status'         => 'nullable|in:not_started,in_progress,on_hold,completed,cancelled',
                'priority'       => 'nullable|in:low,medium,high,urgent',
                'category'       => 'nullable|string|max:100',
                'start_date'     => 'nullable|date',
                'due_date'       => 'nullable|date',
                'budget'         => 'nullable|numeric|min:0',
                'currency'       => 'nullable|string|max:10',
                'notes'          => 'nullable|string|max:5000',
                'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', $userId)],
                'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', $userId)],
                'crm_deal_id'    => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', $userId)],
            ],
            'invoice' => [
                'crm_contact_id'      => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', $userId)],
                'crm_company_id'      => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', $userId)],
                'crm_deal_id'         => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', $userId)],
                'status'              => 'nullable|in:draft,sent,paid,partial,overdue,cancelled',
                'issue_date'          => 'nullable|date',
                'due_date'            => 'nullable|date',
                'currency'            => 'nullable|string|max:10',
                'discount_type'       => 'nullable|in:fixed,percentage',
                'discount_value'      => 'nullable|numeric|min:0',
                'tax_rate'            => 'nullable|numeric|min:0|max:100',
                'notes'               => 'nullable|string|max:5000',
                'from_name'           => 'nullable|string|max:255',
                'from_email'          => 'nullable|email|max:255',
                'from_phone'          => 'nullable|string|max:255',
                'from_address'        => 'nullable|string|max:1000',
                'items'               => 'nullable|array|max:50',
                'items.*.description' => 'required|string|max:500',
                'items.*.quantity'    => 'required|numeric|min:0.01',
                'items.*.unit_price'  => 'required|numeric|min:0',
                'items.*.unit'        => 'nullable|string|max:50',
            ],
            'presentation' => [
                'crm_deal_id' => ["{$req}", Rule::exists('crm_deals', 'id')->where('user_id', $userId)],
                'style'       => 'nullable|in:corporate,modern,minimal,creative',
            ],
            default => throw new InvalidArgumentException("Unknown entity: {$entity}"),
        };
    }
}
