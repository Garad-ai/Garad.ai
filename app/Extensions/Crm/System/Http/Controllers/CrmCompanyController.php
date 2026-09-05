<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmCompanyController extends Controller
{
    public function index(Request $request): View
    {
        $userId = Auth::id();
        $filter = $request->query('filter', 'all');
        $sort = $request->query('sort', 'name');
        $sortDir = in_array($request->query('sort_dir'), ['asc', 'desc']) ? $request->query('sort_dir') : 'asc';

        $query = CrmCompany::query()
            ->where('user_id', $userId)
            ->withCount(['contacts', 'deals']);

        match ($filter) {
            'favorites' => $query->where('is_favorite', true),
            default     => null,
        };

        match ($sort) {
            'name'       => $query->orderBy('name', $sortDir),
            'created_at' => $query->orderBy('created_at', $sortDir),
            'industry'   => $query->orderBy('industry', $sortDir),
            default      => $query->orderBy('name', $sortDir),
        };

        $list = $query->get();

        $totalCompanies = CrmCompany::where('user_id', $userId)->count();
        $totalContacts = $list->sum('contacts_count');
        $totalDeals = $list->sum('deals_count');
        $addedThisWeek = CrmCompany::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $stats = [
            ['label' => __('Total Companies'), 'value' => number_format($totalCompanies), 'icon' => 'tabler-building', 'color' => 'primary'],
            ['label' => __('Total Contacts'), 'value' => number_format($totalContacts), 'icon' => 'tabler-users', 'color' => 'emerald-500'],
            ['label' => __('Total Deals'), 'value' => number_format($totalDeals), 'icon' => 'tabler-briefcase', 'color' => '[#3C82F6]'],
            ['label' => __('Added This Week'), 'value' => number_format($addedThisWeek), 'icon' => 'tabler-building-plus', 'color' => 'secondary'],
        ];

        return view('crm::companies.index', [
            'list'    => $list,
            'stats'   => $stats,
            'sort'    => $sort,
            'sortDir' => $sortDir,
            'filter'  => $filter,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'industry' => 'nullable|string|max:255',
            'website'  => 'nullable|string|max:255',
            'phone'    => 'nullable|string|max:255',
            'email'    => 'nullable|email|max:255',
            'address'  => 'nullable|string|max:1000',
            'city'     => 'nullable|string|max:255',
            'state'    => 'nullable|string|max:255',
            'country'  => 'nullable|string|max:255',
            'notes'    => 'nullable|string|max:5000',
        ]);

        CrmCompany::create(array_merge($validated, ['user_id' => Auth::id()]));

        return response()->json(['message' => __('Company created successfully.'), 'type' => 'success']);
    }

    public function update(Request $request, CrmCompany $company): JsonResponse
    {
        abort_if($company->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'industry' => 'nullable|string|max:255',
            'website'  => 'nullable|string|max:255',
            'phone'    => 'nullable|string|max:255',
            'email'    => 'nullable|email|max:255',
            'address'  => 'nullable|string|max:1000',
            'city'     => 'nullable|string|max:255',
            'state'    => 'nullable|string|max:255',
            'country'  => 'nullable|string|max:255',
            'notes'    => 'nullable|string|max:5000',
        ]);

        $company->update($validated);

        return response()->json(['message' => __('Company updated successfully.'), 'type' => 'success']);
    }

    public function delete(CrmCompany $company): RedirectResponse
    {
        abort_if($company->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        $company->delete();

        return back()->with(['message' => __('Company deleted successfully.'), 'type' => 'success']);
    }

    public function show(CrmCompany $company): View
    {
        abort_if($company->user_id !== Auth::id(), 404);

        $company->load(['contacts', 'deals.stage']);

        return view('crm::companies.show', [
            'item' => $company,
        ]);
    }
}
