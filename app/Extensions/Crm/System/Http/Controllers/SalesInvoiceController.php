<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\SalesInvoice;
use App\Extensions\Crm\System\Models\SalesPayment;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SalesInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'all');
        $sort = $request->query('sort', 'created_at');
        $sortDir = in_array($request->query('sort_dir'), ['asc', 'desc']) ? $request->query('sort_dir') : 'desc';

        $query = SalesInvoice::query()
            ->where('user_id', Auth::id())
            ->with(['contact', 'company']);

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        match ($sort) {
            'due_date' => $query->orderBy('due_date', $sortDir),
            'total'    => $query->orderBy('total', $sortDir),
            'status'   => $query->orderBy('status', $sortDir),
            default    => $query->orderBy('created_at', $sortDir),
        };

        $userId = Auth::id();

        // Stats (always unfiltered)
        $allInvoices = SalesInvoice::where('user_id', $userId)->get();
        $totalInvoices = $allInvoices->count();
        $totalRevenue = $allInvoices->whereIn('status', ['paid', 'partial'])->sum('total');
        $unpaidCount = $allInvoices->whereIn('status', ['sent', 'overdue'])->count();
        $overdueCount = $allInvoices->where('status', 'overdue')->count();

        $stats = [
            ['label' => __('Total Invoices'), 'value' => number_format($totalInvoices), 'icon' => 'tabler-file-invoice', 'color' => 'primary'],
            ['label' => __('Revenue'), 'value' => number_format($totalRevenue, 0), 'prefix' => '$', 'icon' => 'tabler-currency-dollar', 'color' => 'emerald-500'],
            ['label' => __('Unpaid'), 'value' => number_format($unpaidCount), 'icon' => 'tabler-clock', 'color' => 'amber-500'],
            ['label' => __('Overdue'), 'value' => number_format($overdueCount), 'icon' => 'tabler-alert-triangle', 'color' => 'red-500'],
        ];

        return view('crm::sales.invoices.index', [
            'list'      => $query->get(),
            'contacts'  => CrmContact::where('user_id', $userId)->orderBy('first_name')->get(),
            'companies' => CrmCompany::where('user_id', $userId)->orderBy('name')->get(),
            'deals'     => CrmDeal::where('user_id', $userId)->orderBy('title')->get(),
            'filter'    => $filter,
            'sort'      => $sort,
            'sortDir'   => $sortDir,
            'stats'     => $stats,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'crm_deal_id'    => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', Auth::id())],
            'issue_date'     => 'required|date',
            'due_date'       => 'nullable|date',
            'currency'       => 'nullable|string|max:10',
            'notes'          => 'nullable|string|max:5000',
            'from_name'      => 'nullable|string|max:255',
            'from_email'     => 'nullable|email|max:255',
            'from_phone'     => 'nullable|string|max:255',
            'from_address'   => 'nullable|string|max:2000',
            'discount_type'  => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_rate'       => 'nullable|numeric|min:0|max:100',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['invoice_number'] = SalesInvoice::generateNumber(Auth::id());
        $validated['status'] = 'draft';
        $validated['currency'] = $validated['currency'] ?? setting('crm_default_currency', 'USD');
        $validated['subtotal'] = 0;
        $validated['tax_amount'] = 0;
        $validated['total'] = 0;
        $validated['amount_paid'] = 0;

        $invoice = SalesInvoice::create($validated);

        return response()->json([
            'message'    => __('Invoice created successfully.'),
            'type'       => 'success',
            'invoice_id' => $invoice->id,
        ]);
    }

    public function show(SalesInvoice $invoice): View
    {
        abort_if($invoice->user_id !== Auth::id(), 404);

        $invoice->load(['contact', 'company', 'deal', 'items', 'payments']);

        return view('crm::sales.invoices.show', [
            'item'      => $invoice,
            'contacts'  => CrmContact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'companies' => CrmCompany::where('user_id', Auth::id())->orderBy('name')->get(),
            'deals'     => CrmDeal::where('user_id', Auth::id())->orderBy('title')->get(),
        ]);
    }

    public function update(Request $request, SalesInvoice $invoice): JsonResponse
    {
        abort_if($invoice->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'crm_deal_id'    => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', Auth::id())],
            'issue_date'     => 'required|date',
            'due_date'       => 'nullable|date',
            'currency'       => 'nullable|string|max:10',
            'notes'          => 'nullable|string|max:5000',
            'from_name'      => 'nullable|string|max:255',
            'from_email'     => 'nullable|email|max:255',
            'from_phone'     => 'nullable|string|max:255',
            'from_address'   => 'nullable|string|max:2000',
            'discount_type'  => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'tax_rate'       => 'nullable|numeric|min:0|max:100',
        ]);

        $invoice->update($validated);
        $invoice->recalculateTotals();

        return response()->json(['message' => __('Invoice updated successfully.'), 'type' => 'success']);
    }

    public function delete(SalesInvoice $invoice): RedirectResponse|JsonResponse
    {
        abort_if($invoice->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            if (request()->ajax()) {
                return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
            }

            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $invoice->delete();

        if (request()->ajax()) {
            return response()->json([
                'message'  => __('Invoice deleted successfully.'),
                'type'     => 'success',
                'redirect' => route('dashboard.user.sales.invoices.index'),
            ]);
        }

        return back()->with(['message' => __('Invoice deleted successfully.'), 'type' => 'success']);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'invoice_id' => 'required|exists:sales_invoices,id',
            'status'     => 'required|in:draft,sent,paid,overdue,cancelled',
        ]);

        $invoice = SalesInvoice::where('id', $validated['invoice_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $invoice->update(['status' => $validated['status']]);

        return response()->json(['message' => __('Invoice status updated.'), 'type' => 'success']);
    }

    public function saveItems(Request $request, SalesInvoice $invoice): JsonResponse
    {
        abort_if($invoice->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'items'               => 'present|array',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity'    => 'required|numeric|min:0',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.unit'        => 'nullable|string|max:50',
            'tax_rate'            => 'nullable|numeric|min:0|max:100',
        ]);

        $invoice->items()->delete();

        $subtotal = 0;
        $sortOrder = 0;

        foreach ($validated['items'] ?? [] as $itemData) {
            $lineTotal = round($itemData['quantity'] * $itemData['unit_price'], 2);
            $subtotal += $lineTotal;

            $invoice->items()->create([
                'description' => $itemData['description'],
                'quantity'    => $itemData['quantity'],
                'unit'        => $itemData['unit'] ?? null,
                'unit_price'  => $itemData['unit_price'],
                'total'       => $lineTotal,
                'sort_order'  => $sortOrder++,
            ]);
        }

        $taxRate = $validated['tax_rate'] ?? $invoice->tax_rate;

        $discountAmount = $invoice->discount_type === 'percentage'
            ? $subtotal * ((float) $invoice->discount_value / 100)
            : (float) $invoice->discount_value;

        $afterDiscount = max(0, $subtotal - $discountAmount);
        $taxAmount = round($afterDiscount * ($taxRate / 100), 2);

        $invoice->update([
            'subtotal'   => $subtotal,
            'tax_rate'   => $taxRate,
            'tax_amount' => $taxAmount,
            'total'      => $afterDiscount + $taxAmount,
        ]);

        return $this->freshInvoiceJson($invoice);
    }

    // ── Payments ─────────────────────────────────────────────────

    public function storePayment(Request $request, SalesInvoice $invoice): JsonResponse
    {
        abort_if($invoice->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_date'   => 'required|date',
            'payment_method' => 'nullable|in:bank_transfer,credit_card,cash,check,other',
            'reference'      => 'nullable|string|max:255',
            'notes'          => 'nullable|string|max:2000',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['currency'] = $invoice->currency;
        $validated['crm_contact_id'] = $invoice->crm_contact_id;
        $validated['crm_company_id'] = $invoice->crm_company_id;
        $validated['status'] = 'completed';

        $invoice->payments()->create($validated);
        $invoice->recalculateTotals();

        // Auto-mark as paid if fully paid
        $invoice->refresh();
        if ($invoice->balance_due <= 0 && $invoice->status !== 'paid' && $invoice->status !== 'cancelled') {
            $invoice->update(['status' => 'paid']);
        }

        return $this->freshInvoiceJson($invoice);
    }

    public function deletePayment(SalesInvoice $invoice, SalesPayment $payment): JsonResponse
    {
        abort_if($invoice->user_id !== Auth::id(), 404);
        abort_if($payment->sales_invoice_id !== $invoice->id, 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $payment->delete();
        $invoice->recalculateTotals();

        // Revert paid status if underpaid
        $invoice->refresh();
        if ($invoice->balance_due > 0 && $invoice->status === 'paid') {
            $invoice->update(['status' => 'sent']);
        }

        return $this->freshInvoiceJson($invoice);
    }

    // ── PDF Download ─────────────────────────────────────────────

    public function downloadPdf(SalesInvoice $invoice): Response
    {
        abort_if($invoice->user_id !== Auth::id(), 404);

        $invoice->load(['contact', 'company', 'deal', 'items', 'payments']);

        $pdf = Pdf::loadView('crm::sales.invoices.pdf', [
            'invoice' => $invoice,
        ])->setPaper('a4');

        $filename = $invoice->invoice_number . '.pdf';

        return $pdf->download($filename);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function freshInvoiceJson(SalesInvoice $invoice): JsonResponse
    {
        $invoice->refresh();
        $invoice->load(['items', 'payments']);

        return response()->json([
            'message' => __('Updated successfully.'),
            'type'    => 'success',
            'invoice' => [
                'id'             => $invoice->id,
                'subtotal'       => (float) $invoice->subtotal,
                'discount_type'  => $invoice->discount_type,
                'discount_value' => (float) $invoice->discount_value,
                'tax_rate'       => (float) $invoice->tax_rate,
                'tax_amount'     => (float) $invoice->tax_amount,
                'total'          => (float) $invoice->total,
                'amount_paid'    => (float) $invoice->amount_paid,
                'balance_due'    => (float) $invoice->balance_due,
                'status'         => $invoice->status,
                'items'          => $invoice->items->map(fn ($i) => [
                    'id'          => $i->id,
                    'description' => $i->description,
                    'quantity'    => (float) $i->quantity,
                    'unit'        => $i->unit,
                    'unit_price'  => (float) $i->unit_price,
                    'total'       => (float) $i->total,
                    'sort_order'  => $i->sort_order,
                ])->values(),
                'payments' => $invoice->payments->map(fn ($p) => [
                    'id'             => $p->id,
                    'amount'         => (float) $p->amount,
                    'payment_date'   => $p->payment_date->format('Y-m-d'),
                    'payment_method' => $p->payment_method,
                    'reference'      => $p->reference,
                    'notes'          => $p->notes,
                ])->values(),
            ],
        ]);
    }
}
