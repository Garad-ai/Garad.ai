<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers;

use App\Extensions\AIAgent\System\Http\Controllers\Concerns\ResolvesTemplateAvatars;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Services\UnreadMessagesService;
use App\Extensions\AIAgentGmail\System\Models\AIAgentConnector;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AIAgentDashboardController extends Controller
{
    use ResolvesTemplateAvatars;

    public function __construct(
        private readonly UnreadMessagesService $unreadMessages,
    ) {}

    public function __invoke()
    {
        $userId = Auth::id();

        $userName = explode(' ', Auth::user()->name ?? '')[0];

        return view('ai-agent::dashboard.index', [
            'title'            => __('AI Agent'),
            'userName'         => $userName,
            'workflows'		      => AIAgentWorkflow::query()->with('channel')->where('user_id', $userId)->latest()->limit(5)->get(),
            'templates'        => collect(config('ai-agent.workflow_templates', []))
                ->map(fn ($t, $k) => array_merge(['key' => $k, 'avatar' => $this->templateAvatar($k), 'trigger' => $t['config']['trigger_type'] ?? 'schedule'], $t)),
            'unreadCount'      => $this->unreadMessages->unreadCount($userId),
            'connectors'       => $this->connectorData(),
        ]);
    }

    private function connectorData(): array
    {
        if (! class_exists(AIAgentConnector::class)) {
            return [];
        }

        return AIAgentConnector::query()
            ->forUser(Auth::user())
            ->get()
            ->map(function ($connector): array {
                $reconnectUrl = null;

                try {
                    $reconnectUrl = match ($connector->type) {
                        'gmail'   => route('dashboard.user.ai-agent.connectors.gmail.redirect'),
                        'outlook' => route('dashboard.user.ai-agent.connectors.outlook.redirect'),
                        default   => null,
                    };
                } catch (Throwable) {
                }

                return [
                    'id'            => $connector->id,
                    'type'          => $connector->type,
                    'name'          => $connector->getCredential('name'),
                    'email'         => $connector->getCredential('email'),
                    'picture'       => $connector->getCredential('picture'),
                    'is_active'     => $connector->is_active && ($connector->expires_at?->isFuture() ?? true),
                    'reconnect_url' => $reconnectUrl,
                    'delete_url'    => route('dashboard.user.ai-agent.connectors.destroy', $connector),
                ];
            })
            ->all();
    }
}
