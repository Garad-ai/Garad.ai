@php
    $dubbingViewer = isset($user) && $user ? $user : auth()?->user();
    $dubbingPlan = isset($plan) && $plan && $plan->exists
        ? $plan
        : ($dubbingViewer ? $dubbingViewer->relationPlan : null);

    $showDubbingRow = false;
    $dubbingUnlimited = false;
    $dubbingCredits = 0;
    $dubbingLabel = __('Video Dubbing (seconds)');
    $dubbingIsAdminView = ! (isset($plan) && $plan && $plan->exists)
        && $dubbingViewer
        && method_exists($dubbingViewer, 'isAdmin')
        && $dubbingViewer->isAdmin();

    if ($dubbingIsAdminView) {
        $showDubbingRow = true;
        $dubbingUnlimited = true;
    } elseif ($dubbingPlan && $dubbingPlan->exists && $dubbingPlan->checkOpenAiItem(\App\Enums\Introduction::AI_VIDEO_DUBBING->value)) {
        $dubbingLimit = (int) ($dubbingPlan->video_dubbing_seconds_limit ?? -1);

        if ($dubbingLimit !== 0) {
            $dubbingUnlimited = $dubbingLimit === -1;

            if (isset($plan) && $plan && $plan->exists) {
                $dubbingCredits = $dubbingUnlimited ? 0 : $dubbingLimit;
                $showDubbingRow = true;
            } else {
                $dubbingUserId = isset($user) && $user ? $user->id : auth()?->id();

                if ($dubbingUserId) {
                    $dubbingUsed = (int) \App\Extensions\VideoDubbing\System\Models\VideoDubbing::query()
                        ->where('user_id', $dubbingUserId)
                        ->whereIn('status', ['complete', 'pending', 'running'])
                        ->where('created_at', '>=', now()->startOfMonth())
                        ->sum('duration_seconds');

                    $dubbingCredits = $dubbingUnlimited ? 0 : max(0, $dubbingLimit - $dubbingUsed);
                    $showDubbingRow = $dubbingUnlimited || $dubbingCredits > 0;
                }
            }
        }
    }
@endphp

@if ($showDubbingRow)
    <tr>
        <td class="flex justify-between border-b p-2">
            {{ $dubbingLabel }}
        </td>
        <td class="border p-2 text-end">
            {{ $dubbingUnlimited ? __('Unlimited') : $dubbingCredits }}
        </td>
    </tr>
@endif
