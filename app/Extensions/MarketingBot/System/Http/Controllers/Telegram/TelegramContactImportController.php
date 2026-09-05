<?php

declare(strict_types=1);

namespace App\Extensions\MarketingBot\System\Http\Controllers\Telegram;

use App\Events\ContactCapturedEvent;
use App\Extensions\MarketingBot\System\Http\Requests\CsvImportRequest;
use App\Extensions\MarketingBot\System\Models\Telegram\TelegramGroupSubscriber;
use App\Extensions\MarketingBot\System\Services\CsvImportService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TelegramContactImportController extends Controller
{
    public function __construct(private readonly CsvImportService $csvImportService) {}

    public function headers(CsvImportRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('CSV import is disabled in demo mode.'),
            ]);
        }

        if (! $request->isCsvFile()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('Only CSV files are allowed.'),
            ]);
        }

        $headers = $this->csvImportService->readCsvHeaders($request->file('file'));

        if ($headers === null) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('Could not read CSV file.'),
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'headers' => $headers,
        ]);
    }

    public function preview(CsvImportRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('CSV import is disabled in demo mode.'),
            ]);
        }

        if (! $request->isCsvFile()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('Only CSV files are allowed.'),
            ]);
        }

        $result = $this->csvImportService->parseTelegram(
            $request->file('file'),
            Auth::id(),
            $request->input('mapping', []),
        );

        if (isset($result['error'])) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['error'],
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'valid'   => $result['valid'],
            'invalid' => $result['invalid'],
        ]);
    }

    public function import(CsvImportRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('CSV import is disabled in demo mode.'),
            ]);
        }

        if (! $request->isCsvFile()) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('Only CSV files are allowed.'),
            ]);
        }

        $result = $this->csvImportService->parseTelegram(
            $request->file('file'),
            Auth::id(),
            $request->input('mapping', []),
        );

        if (isset($result['error'])) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['error'],
            ]);
        }

        $validRows = $result['valid'];

        if (empty($validRows)) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('No valid contacts found in the CSV file.'),
            ]);
        }

        $userId = Auth::id();
        $groupId = $request->integer('group_id') ?: null;
        $now = now();

        $toInsert = array_map(static fn (array $row) => [
            'user_id'      => $userId,
            'name'         => $row['name'],
            'username'     => $row['username'] !== '' ? $row['username'] : null,
            'phone'        => $row['phone'],
            'group_id'     => $groupId,
            'status'       => true,
            'is_bot'       => false,
            'is_admin'     => false,
            'is_blacklist' => false,
            'created_at'   => $now,
            'updated_at'   => $now,
        ], $validRows);

        TelegramGroupSubscriber::query()->insert($toInsert);

        foreach ($validRows as $row) {
            ContactCapturedEvent::dispatch(
                $userId,
                (string) $row['name'],
                null,
                $row['phone'] !== '' ? $row['phone'] : null,
                null,
                'marketing_telegram',
            );
        }

        return response()->json([
            'status'  => 'success',
            'message' => trans(':count contact(s) imported successfully.', ['count' => count($toInsert)]),
            'count'   => count($toInsert),
        ]);
    }
}
