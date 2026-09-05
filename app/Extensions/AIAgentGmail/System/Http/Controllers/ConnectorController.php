<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Http\Controllers;

use App\Extensions\AIAgentGmail\System\Models\AIAgentConnector;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConnectorController extends Controller
{
    public function destroy(Request $request, AIAgentConnector $connector): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $connector);

        $connector->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Connector disconnected.')]);
        }

        return redirect()->route('dashboard.user.ai-agent.workflows.index')
            ->with(['type' => 'success', 'message' => __('Connector disconnected.')]);
    }
}
