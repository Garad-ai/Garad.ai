<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Extensions\AIChatPro\System\Connectors\ConnectorPlanGate;
use App\Extensions\AIChatPro\System\Connectors\ConnectorRegistry;
use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ConnectorController extends Controller
{
    public function __construct(private readonly ConnectorRegistry $registry) {}

    public function index(Request $request): JsonResponse
    {
        $connectors = AIChatProConnector::query()
            ->where('user_id', Auth::id())
            ->get()
            ->keyBy('type');

        $available = [];
        $hasInactive = false;

        $plan = ConnectorPlanGate::resolvePlan(Auth::user());

        foreach ($this->registry->enabled() as $key => $definition) {
            if (! ConnectorPlanGate::allowsForPlan($plan, $key)) {
                continue;
            }

            $connector = $connectors->get($key);
            $isActive = $connector?->is_active && ($connector->expires_at === null || $connector->expires_at->isFuture());

            if ($connector && ! $isActive) {
                $hasInactive = true;
            }

            $available[] = [
                'key'              => $key,
                'label'            => $definition->label(),
                'description'      => $definition->description(),
                'details'          => $definition->details(),
                'permissions'      => $definition->permissions(),
                'redirect_url'     => $definition->redirectRoute(),
                'pre_consent_url'  => route('dashboard.user.ai-chat-pro.connectors.pre-consent', ['key' => $key]),
                'is_connected'     => (bool) $connector,
                'is_active'        => (bool) $isActive,
                'is_paused'        => (bool) ($connector?->is_paused),
                'connector_id'     => $connector?->id,
                'connection_uuid'  => $connector?->uuid,
                'connected_email'  => $connector?->getCredential('email'),
                'connected_name'   => $connector?->getCredential('name'),
                'picture'          => $connector?->getCredential('picture'),
                'connected_at'     => $connector?->connected_at?->toDateTimeString(),
                'disconnect_url'   => $connector
                    ? route('dashboard.user.ai-chat-pro.connectors.destroy', $connector)
                    : null,
                'access_url'       => $connector
                    ? route('dashboard.user.ai-chat-pro.connectors.access.show', $connector)
                    : null,
                'pause_url'        => $connector
                    ? route('dashboard.user.ai-chat-pro.connectors.toggle-pause', $connector)
                    : null,
            ];
        }

        return response()->json([
            'connectors'   => $available,
            'has_inactive' => $hasInactive,
        ]);
    }

    public function destroy(Request $request, AIChatProConnector $connector): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $connector);

        $definition = $this->registry->get($connector->type);

        if ($definition) {
            try {
                $definition->revokeToken($connector);
            } catch (Throwable $exception) {
                Log::warning('[connectors] Provider-side revocation failed; deleting locally anyway.', [
                    'connector_id' => $connector->id,
                    'type'         => $connector->type,
                    'message'      => $exception->getMessage(),
                ]);
            }
        }

        $connector->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Connector disconnected.')]);
        }

        return back()->with(['type' => 'success', 'message' => __('Connector disconnected.')]);
    }

    public function preConsent(Request $request, string $key): View|RedirectResponse
    {
        $definition = $this->registry->get($key);

        if (! $definition || ! $definition->isEnabled()) {
            throw new NotFoundHttpException('Connector not available.');
        }

        if (! ConnectorPlanGate::allows(Auth::user(), $key)) {
            throw new NotFoundHttpException('Connector not available on your plan.');
        }

        return view('ai-chat-pro::connectors.pre-consent', [
            'definition' => $definition,
            'popup'      => $request->boolean('popup'),
        ]);
    }

    public function accessShow(Request $request, AIChatProConnector $connector): JsonResponse|View
    {
        $this->authorize('view', $connector);

        $definition = $this->registry->get($connector->type);

        if (! $definition) {
            throw new NotFoundHttpException('Connector definition not found.');
        }

        try {
            $accessOptions = $definition->accessOptions($connector);
        } catch (Throwable $exception) {
            Log::warning('[connectors] accessOptions threw', [
                'connector_id' => $connector->id,
                'message'      => $exception->getMessage(),
            ]);
            $accessOptions = [];
        }

        $payload = [
            'connector_id'   => $connector->id,
            'connector_type' => $connector->type,
            'label'          => $definition->label(),
            'access'         => $accessOptions,
            'selected'       => (array) ($connector->selected_access ?? []),
        ];

        if ($request->expectsJson() || $request->boolean('json')) {
            return response()->json($payload);
        }

        return view('ai-chat-pro::connectors.access-selection', [
            'definition' => $definition,
            'connector'  => $connector,
            'access'     => $accessOptions,
            'selected'   => (array) ($connector->selected_access ?? []),
            'popup'      => $request->boolean('popup'),
        ]);
    }

    public function accessUpdate(Request $request, AIChatProConnector $connector): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $connector);

        $data = $request->validate([
            'selection'    => ['array'],
            'selection.*'  => ['string'],
            'recent_only'  => ['nullable', 'boolean'],
        ]);

        $definition = $this->registry->get($connector->type);

        if (! $definition) {
            throw new NotFoundHttpException('Connector definition not found.');
        }

        try {
            $accessOptions = $definition->accessOptions($connector);
        } catch (Throwable) {
            $accessOptions = [];
        }

        $type = (string) ($accessOptions['type'] ?? 'items');
        $selection = array_values(array_filter((array) ($data['selection'] ?? []), fn ($v) => is_string($v) && $v !== ''));

        $connector->selected_access = [
            $type         => $selection,
            'recent_only' => (bool) ($data['recent_only'] ?? false),
        ];
        $connector->save();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Access updated.')]);
        }

        return back()->with(['type' => 'success', 'message' => __('Access updated.')]);
    }

    public function togglePause(Request $request, AIChatProConnector $connector): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $connector);

        $connector->is_paused = ! $connector->is_paused;
        $connector->save();

        $message = $connector->is_paused
            ? __('Connector paused. The AI will not use it until you resume it.')
            : __('Connector resumed.');

        if ($request->expectsJson()) {
            return response()->json([
                'is_paused' => $connector->is_paused,
                'message'   => $message,
            ]);
        }

        return back()->with(['type' => 'success', 'message' => $message]);
    }
}
