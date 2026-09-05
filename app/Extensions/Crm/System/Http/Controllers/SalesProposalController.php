<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\SalesProposal;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SalesProposalController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'all');
        $sort = $request->query('sort', 'created_at');
        $sortDir = in_array($request->query('sort_dir'), ['asc', 'desc']) ? $request->query('sort_dir') : 'desc';

        $query = SalesProposal::query()
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

        return view('crm::sales.proposals.index', [
            'list'      => $query->get(),
            'contacts'  => CrmContact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'companies' => CrmCompany::where('user_id', Auth::id())->orderBy('name')->get(),
            'deals'     => CrmDeal::where('user_id', Auth::id())->orderBy('title')->get(),
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
            'crm_deal_id'    => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', Auth::id())],
            'status'         => 'nullable|in:draft,sent,accepted,rejected,expired',
            'issue_date'     => 'required|date',
            'valid_until'    => 'nullable|date',
            'currency'       => 'nullable|string|max:10',
            'content'        => 'nullable|string|max:10000',
            'notes'          => 'nullable|string|max:5000',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['proposal_number'] = SalesProposal::generateNumber(Auth::id());

        $proposal = SalesProposal::create($validated);

        return response()->json([
            'message'  => __('Proposal created successfully.'),
            'type'     => 'success',
            'redirect' => route('dashboard.user.sales.proposals.show', $proposal->id),
        ]);
    }

    public function show(SalesProposal $proposal): View
    {
        abort_if($proposal->user_id !== Auth::id(), 404);

        $proposal->load(['contact', 'company', 'deal', 'items']);

        return view('crm::sales.proposals.show', [
            'item'      => $proposal,
            'contacts'  => CrmContact::where('user_id', Auth::id())->orderBy('first_name')->get(),
            'companies' => CrmCompany::where('user_id', Auth::id())->orderBy('name')->get(),
            'deals'     => CrmDeal::where('user_id', Auth::id())->orderBy('title')->get(),
        ]);
    }

    public function update(Request $request, SalesProposal $proposal): JsonResponse
    {
        abort_if($proposal->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'crm_contact_id' => ['nullable', Rule::exists('crm_contacts', 'id')->where('user_id', Auth::id())],
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'crm_deal_id'    => ['nullable', Rule::exists('crm_deals', 'id')->where('user_id', Auth::id())],
            'status'         => 'nullable|in:draft,sent,accepted,rejected,expired',
            'issue_date'     => 'required|date',
            'valid_until'    => 'nullable|date',
            'currency'       => 'nullable|string|max:10',
            'content'        => 'nullable|string|max:10000',
            'notes'          => 'nullable|string|max:5000',
        ]);

        $proposal->update($validated);

        return response()->json(['message' => __('Proposal updated successfully.'), 'type' => 'success']);
    }

    public function delete(SalesProposal $proposal): RedirectResponse|JsonResponse
    {
        abort_if($proposal->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            if (request()->ajax()) {
                return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
            }

            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $proposal->delete();

        if (request()->ajax()) {
            return response()->json([
                'message'  => __('Proposal deleted successfully.'),
                'type'     => 'success',
                'redirect' => route('dashboard.user.sales.proposals.index'),
            ]);
        }

        return back()->with(['message' => __('Proposal deleted successfully.'), 'type' => 'success']);
    }

    public function saveItems(Request $request, SalesProposal $proposal): JsonResponse
    {
        abort_if($proposal->user_id !== Auth::id(), 404);

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

        $proposal->items()->delete();

        $subtotal = 0;

        foreach ($validated['items'] ?? [] as $itemData) {
            $lineTotal = round($itemData['quantity'] * $itemData['unit_price'], 2);
            $subtotal += $lineTotal;

            $proposal->items()->create([
                'description' => $itemData['description'],
                'quantity'    => $itemData['quantity'],
                'unit_price'  => $itemData['unit_price'],
                'total'       => $lineTotal,
            ]);
        }

        $taxRate = $validated['tax_rate'] ?? $proposal->tax_rate;
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        $proposal->update([
            'subtotal'   => $subtotal,
            'tax_rate'   => $taxRate,
            'tax_amount' => $taxAmount,
            'total'      => $subtotal + $taxAmount,
        ]);

        return response()->json(['message' => __('Proposal items saved successfully.'), 'type' => 'success']);
    }
}
