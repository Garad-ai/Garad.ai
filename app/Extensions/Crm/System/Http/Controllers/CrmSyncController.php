<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Services\CrmContactSyncService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmSyncController extends Controller
{
    private const SOURCES = ['marketing_whatsapp', 'marketing_telegram', 'chatbot'];

    private const BATCH_SIZE = 100;

    /**
     * Synchronously sync one batch of a source's contacts into the CRM.
     * The client calls this repeatedly with an increasing offset until
     * the response reports `done`, driving a progress bar.
     */
    public function backfill(string $source, Request $request, CrmContactSyncService $syncService): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => __('This feature is disabled in demo mode.'),
            ], 422);
        }

        if (! in_array($source, self::SOURCES, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Unknown sync source.'),
            ], 422);
        }

        if (! $syncService->isEnabled($source)) {
            return response()->json([
                'status'  => 'error',
                'message' => __('Contact sync for this source is disabled.'),
            ], 422);
        }

        $modelClass = $syncService->modelClassFor($source);

        if ($modelClass === null) {
            return response()->json([
                'status'  => 'error',
                'message' => __('The source extension is not available.'),
            ], 422);
        }

        $userId = Auth::id();
        $offset = max(0, $request->integer('offset'));

        $total = $modelClass::query()->where('user_id', $userId)->count();

        $records = $modelClass::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->offset($offset)
            ->limit(self::BATCH_SIZE)
            ->get();

        $synced = 0;

        foreach ($records as $record) {
            if ($syncService->syncRecord($record, $source)) {
                $synced++;
            }
        }

        $processed = min($offset + $records->count(), $total);

        return response()->json([
            'status'      => 'success',
            'total'       => $total,
            'processed'   => $processed,
            'batchSynced' => $synced,
            'done'        => $records->count() < self::BATCH_SIZE,
        ]);
    }
}
