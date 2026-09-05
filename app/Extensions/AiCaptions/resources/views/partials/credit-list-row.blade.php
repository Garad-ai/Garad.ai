@php
    $captionsViewer = isset($user) && $user ? $user : auth()?->user();
    $captionsPlan = isset($plan) && $plan && $plan->exists
        ? $plan
        : ($captionsViewer ? $captionsViewer->relationPlan : null);

    $showCaptionsRow = false;
    $captionsUnlimited = false;
    $captionsCredits = 0;
    $captionsLabel = __('AI Captions (minutes)');
    $captionsIsAdminView = ! (isset($plan) && $plan && $plan->exists)
        && $captionsViewer
        && method_exists($captionsViewer, 'isAdmin')
        && $captionsViewer->isAdmin();

    if ($captionsIsAdminView) {
        $showCaptionsRow = true;
        $captionsUnlimited = true;
    } elseif ($captionsPlan && $captionsPlan->exists && (bool) ($captionsPlan->ai_captions_access ?? false)) {
        $captionsLimitMinutes = (int) ($captionsPlan->ai_captions_minutes ?? config('aicaptions.default_monthly_minutes', 30));

        if ($captionsLimitMinutes !== 0) {
            $captionsUnlimited = $captionsLimitMinutes === -1;

            if (isset($plan) && $plan && $plan->exists) {
                $captionsCredits = $captionsUnlimited ? 0 : $captionsLimitMinutes;
                $showCaptionsRow = true;
            } else {
                $captionsUserId = isset($user) && $user ? $user->id : auth()?->id();

                if ($captionsUserId) {
                    $captionsUsedSeconds = (int) \App\Extensions\AiCaptions\System\Models\AiCaptionVideo::query()
                        ->where('user_id', $captionsUserId)
                        ->where('usage_deducted', true)
                        ->where('created_at', '>=', now()->startOfMonth())
                        ->sum('duration_seconds');

                    $captionsUsedMinutes = (int) floor($captionsUsedSeconds / 60);
                    $captionsCredits = $captionsUnlimited ? 0 : max(0, $captionsLimitMinutes - $captionsUsedMinutes);
                    $showCaptionsRow = $captionsUnlimited || $captionsCredits > 0;
                }
            }
        }
    }
@endphp

@if ($showCaptionsRow)
    <tr>
        <td class="flex justify-between border-b p-2">
            {{ $captionsLabel }}
        </td>
        <td class="border p-2 text-end">
            {{ $captionsUnlimited ? __('Unlimited') : $captionsCredits }}
        </td>
    </tr>
@endif
