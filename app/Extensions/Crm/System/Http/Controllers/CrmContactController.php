<?php

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CrmContactController extends Controller
{
    public function index(Request $request): View
    {
        $userId = Auth::id();
        $filter = $request->query('filter', 'all');
        $sort = $request->query('sort', 'first_name');
        $sortDir = in_array($request->query('sort_dir'), ['asc', 'desc']) ? $request->query('sort_dir') : 'asc';

        $query = CrmContact::query()
            ->where('user_id', $userId)
            ->with('company');

        // Apply filter
        match ($filter) {
            'favorites' => $query->where('is_favorite', true),
            'active'    => $query->where('status', 'active'),
            'inactive'  => $query->where('status', 'inactive'),
            default     => null,
        };

        // Apply sort
        match ($sort) {
            'first_name'  => $query->orderBy('first_name', $sortDir),
            'created_at'  => $query->orderBy('created_at', $sortDir),
            'company'     => $query->orderBy(
                CrmCompany::select('name')
                    ->whereColumn('crm_companies.id', 'crm_contacts.crm_company_id')
                    ->limit(1),
                $sortDir
            ),
            'status'      => $query->orderBy('status', $sortDir),
            default       => $query->orderBy('first_name', $sortDir),
        };

        $list = $query->get();

        $totalContacts = CrmContact::where('user_id', $userId)->count();
        $activeContacts = CrmContact::where('user_id', $userId)->where('status', 'active')->count();
        $withDeals = CrmContact::where('user_id', $userId)
            ->whereHas('deals')
            ->count();
        $addedThisWeek = CrmContact::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $stats = [
            ['label' => __('Total Contacts'), 'value' => number_format($totalContacts), 'icon' => 'tabler-address-book', 'color' => 'primary'],
            ['label' => __('Active'), 'value' => number_format($activeContacts), 'icon' => 'tabler-user-check', 'color' => 'emerald-500'],
            ['label' => __('With Deals'), 'value' => number_format($withDeals), 'icon' => 'tabler-briefcase', 'color' => '[#3C82F6]'],
            ['label' => __('Added This Week'), 'value' => number_format($addedThisWeek), 'icon' => 'tabler-user-plus', 'color' => 'secondary'],
        ];

        return view('crm::contacts.index', [
            'list'      => $list,
            'companies' => CrmCompany::where('user_id', $userId)->orderBy('name')->get(),
            'stats'     => $stats,
            'filter'    => $filter,
            'sort'      => $sort,
            'sortDir'   => $sortDir,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'avatar'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:255',
            'job_title'      => 'nullable|string|max:255',
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'status'         => 'nullable|in:active,inactive',
            'notes'          => 'nullable|string|max:5000',
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('crm/avatars', 'public');
        }

        CrmContact::create(array_merge($validated, ['user_id' => Auth::id()]));

        return response()->json(['message' => __('Contact created successfully.'), 'type' => 'success']);
    }

    public function update(Request $request, CrmContact $contact): JsonResponse
    {
        abort_if($contact->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return response()->json(['status' => 'error', 'message' => __('This feature is disabled in demo mode.')], 422);
        }

        $validated = $request->validate([
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'avatar'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:255',
            'job_title'      => 'nullable|string|max:255',
            'crm_company_id' => ['nullable', Rule::exists('crm_companies', 'id')->where('user_id', Auth::id())],
            'status'         => 'nullable|in:active,inactive',
            'notes'          => 'nullable|string|max:5000',
        ]);

        if ($request->hasFile('avatar')) {
            if ($contact->avatar) {
                Storage::disk('public')->delete($contact->avatar);
            }

            $validated['avatar'] = $request->file('avatar')->store('crm/avatars', 'public');
        } else {
            unset($validated['avatar']);
        }

        $contact->update($validated);

        return response()->json(['message' => __('Contact updated successfully.'), 'type' => 'success']);
    }

    public function delete(CrmContact $contact): RedirectResponse
    {
        abort_if($contact->user_id !== Auth::id(), 404);

        if (Helper::appIsDemo()) {
            return back()->with(['type' => 'error', 'message' => __('This feature is disabled in demo mode.')]);
        }

        if ($contact->avatar) {
            Storage::disk('public')->delete($contact->avatar);
        }

        $contact->delete();

        return back()->with(['message' => __('Contact deleted successfully.'), 'type' => 'success']);
    }

    public function show(CrmContact $contact): View
    {
        abort_if($contact->user_id !== Auth::id(), 404);

        $contact->load(['company', 'deals.stage', 'tasks', 'activityNotes.user']);

        // Build system events
        $events = [];

        // Contact created
        $events[] = [
            'icon'  => 'tabler-user-plus',
            'color' => 'primary',
            'title' => __('Contact created'),
            'time'  => $contact->created_at,
        ];

        // Deals created
        foreach ($contact->deals as $deal) {
            $events[] = [
                'icon'  => 'tabler-briefcase',
                'color' => 'secondary',
                'title' => __('Deal created'),
                'desc'  => $deal->title . ($deal->value > 0 ? ' — $' . number_format($deal->value, 0) : ''),
                'time'  => $deal->created_at,
            ];
        }

        // Tasks completed
        foreach ($contact->tasks->whereNotNull('completed_at') as $task) {
            $events[] = [
                'icon'  => 'tabler-circle-check',
                'color' => 'emerald-500',
                'title' => __('Task completed'),
                'desc'  => $task->title,
                'time'  => $task->completed_at,
            ];
        }

        return view('crm::contacts.show', [
            'item'   => $contact,
            'notes'  => $contact->activityNotes,
            'events' => $events,
        ]);
    }
}
