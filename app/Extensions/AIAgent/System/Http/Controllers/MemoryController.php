<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers;

use App\Extensions\AIAgent\System\Exceptions\PlanLimitExceededException;
use App\Extensions\AIAgent\System\Http\Requests\StoreMemoryRequest;
use App\Extensions\AIAgent\System\Http\Requests\UpdateMemoryRequest;
use App\Extensions\AIAgent\System\Memory\MemoryRepository;
use App\Extensions\AIAgent\System\Models\AIAgentMemory;
use App\Extensions\AIAgent\System\Services\AIAgentPlanService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MemoryController extends Controller
{
    public function __construct(
        private readonly MemoryRepository $memoryRepository,
        private readonly AIAgentPlanService $planService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $items = AIAgentMemory::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $items->items(),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ]);
        }

        return view('ai-agent::memory.index', [
            'title'       => __('User Memory'),
            'description' => __('Facts the AI Agent remembers about you across all workflows.'),
            'items'       => $items,
        ]);
    }

    public function store(StoreMemoryRequest $request): JsonResponse
    {
        try {
            $this->planService->checkMemoryLimit(Auth::user());
        } catch (PlanLimitExceededException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $memory = $this->memoryRepository->remember(
            userId: Auth::id(),
            memory: $request->validated('memory'),
        );

        return response()->json([
            'success' => true,
            'message' => __('Memory saved.'),
            'memory'  => array_merge($memory->toArray(), [
                'created_at_formatted' => $memory->created_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function edit(AIAgentMemory $memory): JsonResponse
    {
        $this->authorize('update', $memory);

        return response()->json(['success' => true, 'memory' => $memory]);
    }

    public function update(AIAgentMemory $memory, UpdateMemoryRequest $request): JsonResponse
    {
        $this->authorize('update', $memory);

        $this->memoryRepository->update($memory, $request->validated('memory'));

        return response()->json([
            'success' => true,
            'message' => __('Memory updated.'),
            'memory'  => array_merge($memory->fresh()->toArray(), [
                'created_at_formatted' => $memory->created_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function destroy(AIAgentMemory $memory): JsonResponse
    {
        $this->authorize('delete', $memory);

        if (Helper::appIsDemo()) {
            return response()->json(['success' => false, 'message' => __('This feature is disabled in demo mode.')], 403);
        }

        $this->memoryRepository->forget($memory);

        return response()->json(['success' => true, 'message' => __('Memory deleted.')]);
    }
}
