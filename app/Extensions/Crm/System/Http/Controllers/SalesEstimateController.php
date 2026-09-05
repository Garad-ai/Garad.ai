<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\SalesEstimate;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SalesEstimateController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'all');
        $sort = $request->query('sort', 'created_at');
        $sortDir = in_array($request->query('sort_dir'), ['asc', 'desc']) ? $request->query('sort_dir') : 'desc';

        $query = SalesEstimate::query()
            ->where('user_id', Auth::id())
            ->with(['contact', 'company']);

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        match ($sort) {
            'title'       => $query->orderBy('title', $sortDir),
            'status'      => $query->orderBy('status', $sortDir),
            'valid_until' => $query->orderByRaw("valid_until IS NULL, valid_until {$sortDir}"),
            'total'       => $query->orderBy('total', $sortDir),
            default       => $query->orderBy('created_at', $sortDir),
        };

        return view('crm::sales.estimates.index', [
            'list'      => $query->get(),
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
            'title'          => 'required|string|max:255',
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'status'         => 'nullable|in:draft,sent,accepted,rejected,expired',
            'issue_date'     => 'required|date',
            'valid_until'    => 'nullable|date',
            'currency'       => 'nullable|string|max:10',
            'notes'          => 'nullable|string|max:5000',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['estimate_number'] = SalesEstimate::generateNumber(Auth::id());

        SalesEstimate::create($validated);

        return response()->json(['message' => __('Estimate created successfully.'), 'type' => 'success']);
    }

    public function show(SalesEstimate $estimate): View
    {
        abort_if($estimate->user_id !== Auth::id(), 404);

        $estimate->load(['contact', 'company', 'items']);

        return view('crm::sales.estimates.show', [
            'item'      => $estimate,
            'contacts'  => CrmContact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'companies' => CrmCompany::where('user_id', Auth::id())->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SalesEstimate $estimate): JsonResponse
    {
        abort_if($estimate->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'status'         => 'nullable|in:draft,sent,accepted,rejected,expired',
            'issue_date'     => 'required|date',
            'valid_until'    => 'nullable|date',
            'currency'       => 'nullable|string|max:10',
            'notes'          => 'nullable|string|max:5000',
        ]);

        $estimate->update($validated);

        return response()->json(['message' => __('Estimate updated successfully.'), 'type' => 'success']);
    }

    public function delete(SalesEstimate $estimate): RedirectResponse|JsonResponse
    {
        abort_if($estimate->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            if (request()->ajax()) {
                return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
            }

            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $estimate->delete();

        if (request()->ajax()) {
            return response()->json([
                'message'  => __('Estimate deleted successfully.'),
                'type'     => 'success',
                'redirect' => route('dashboard.user.sales.estimates.index'),
            ]);
        }

        return back()->with(['message' => __('Estimate deleted successfully.'), 'type' => 'success']);
    }

    public function saveItems(Request $request, SalesEstimate $estimate): JsonResponse
    {
        abort_if($estimate->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'items'               => 'present|array',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity'    => 'required|numeric|min:0',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'tax_rate'            => 'nullable|numeric|min:0|max:100',
        ]);

        $estimate->items()->delete();

        $subtotal = 0;

        foreach ($validated['items'] ?? [] as $itemData) {
            $lineTotal = round($itemData['quantity'] * $itemData['unit_price'], 2);
            $subtotal += $lineTotal;

            $estimate->items()->create([
                'description' => $itemData['description'],
                'quantity'    => $itemData['quantity'],
                'unit_price'  => $itemData['unit_price'],
                'total'       => $lineTotal,
            ]);
        }

        $taxRate = $validated['tax_rate'] ?? $estimate->tax_rate;
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        $estimate->update([
            'subtotal'   => $subtotal,
            'tax_rate'   => $taxRate,
            'tax_amount' => $taxAmount,
            'total'      => $subtotal + $taxAmount,
        ]);

        return response()->json(['message' => __('Estimate items saved successfully.'), 'type' => 'success']);
    }
}
