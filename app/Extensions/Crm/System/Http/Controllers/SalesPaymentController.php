<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\SalesInvoice;
use App\Extensions\Crm\System\Models\SalesPayment;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SalesPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'all');
        $sort = $request->query('sort', 'payment_date');
        $sortDir = in_array($request->query('sort_dir'), ['asc', 'desc']) ? $request->query('sort_dir') : 'desc';

        $query = SalesPayment::query()
            ->where('user_id', Auth::id())
            ->with(['invoice', 'contact', 'company']);

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        match ($sort) {
            'amount'       => $query->orderBy('amount', $sortDir),
            'status'       => $query->orderBy('status', $sortDir),
            default        => $query->orderBy('payment_date', $sortDir),
        };

        return view('crm::sales.payments.index', [
            'list'      => $query->get(),
            'invoices'  => SalesInvoice::where('user_id', Auth::id())->orderBy('invoice_number')->get(),
            'contacts'  => CrmContact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'companies' => CrmCompany::where('user_id', Auth::id())->orderBy('name')->get(),
            'sort'      => $sort,
            'sortDir'   => $sortDir,
            'filter'    => $filter,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'sales_invoice_id' => ['nullable', Rule::exists('sales_invoices', 'id')->where('user_id', Auth::id())],
            'crm_contact_id'   => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id'   => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'amount'           => 'required|numeric|min:0',
            'currency'         => 'nullable|string|max:10',
            'payment_date'     => 'required|date',
            'payment_method'   => 'nullable|in:cash,bank_transfer,credit_card,check,other',
            'reference'        => 'nullable|string|max:255',
            'status'           => 'nullable|in:completed,pending,refunded',
            'notes'            => 'nullable|string|max:5000',
        ]);

        SalesPayment::create(array_merge($validated, ['user_id' => Auth::id()]));

        return response()->json(['message' => __('Payment recorded successfully.'), 'type' => 'success']);
    }

    public function update(Request $request, SalesPayment $payment): JsonResponse
    {
        abort_if($payment->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'sales_invoice_id' => ['nullable', Rule::exists('sales_invoices', 'id')->where('user_id', Auth::id())],
            'crm_contact_id'   => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id'   => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'amount'           => 'required|numeric|min:0',
            'currency'         => 'nullable|string|max:10',
            'payment_date'     => 'required|date',
            'payment_method'   => 'nullable|in:cash,bank_transfer,credit_card,check,other',
            'reference'        => 'nullable|string|max:255',
            'status'           => 'nullable|in:completed,pending,refunded',
            'notes'            => 'nullable|string|max:5000',
        ]);

        $payment->update($validated);

        return response()->json(['message' => __('Payment updated successfully.'), 'type' => 'success']);
    }

    public function delete(SalesPayment $payment): RedirectResponse
    {
        abort_if($payment->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $payment->delete();

        return back()->with(['message' => __('Payment deleted successfully.'), 'type' => 'success']);
    }
}
