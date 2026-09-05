<?php

declare(strict_types=1);

namespace App\Extensions\MarketingBot\System\Http\Controllers\Whatsapp;

use App\Events\ContactCapturedEvent;
use App\Extensions\MarketingBot\System\Http\Requests\CsvImportRequest;
use App\Extensions\MarketingBot\System\Models\Whatsapp\ContactList;
use App\Extensions\MarketingBot\System\Services\CsvImportService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContactListImportController extends Controller
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

        $result = $this->csvImportService->parseWhatsapp(
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

        $result = $this->csvImportService->parseWhatsapp(
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

        $includePhones = $request->input('include_phones', []);
        if (! empty($includePhones)) {
            $validRows = array_values(
                array_filter($validRows, fn (array $r) => in_array($r['phone'], $includePhones, true))
            );
        }

        if (empty($validRows)) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('No valid contacts found in the CSV file.'),
            ]);
        }

        $userId = Auth::id();
        $segmentId = $request->integer('segment_id') ?: null;
        $contactId = $request->integer('contact_id') ?: null;
        $now = now();
        $phones = array_column($validRows, 'phone');

        $toInsert = array_map(static fn (array $row) => [
            'user_id'      => $userId,
            'name'         => $row['name'],
            'phone'        => $row['phone'],
            'country_code' => $row['country_code'],
            'created_at'   => $now,
            'updated_at'   => $now,
        ], $validRows);

        ContactList::query()->insert($toInsert);

        foreach ($validRows as $row) {
            ContactCapturedEvent::dispatch(
                $userId,
                (string) $row['name'],
                null,
                $row['phone'],
                $row['country_code'] !== null ? (int) $row['country_code'] : null,
                'marketing_whatsapp',
            );
        }

        if ($segmentId !== null || $contactId !== null) {
            $insertedIds = ContactList::query()
                ->where('user_id', $userId)
                ->whereIn('phone', $phones)
                ->pluck('id');

            if ($segmentId !== null) {
                DB::table('ext_contact_list_segment')->insert(
                    $insertedIds->map(fn ($id) => [
                        'contact_list_id' => $id,
                        'segment_id'      => $segmentId,
                    ])->all()
                );
            }

            if ($contactId !== null) {
                DB::table('ext_contact_list_contact')->insert(
                    $insertedIds->map(fn ($id) => [
                        'contact_list_id' => $id,
                        'contact_id'      => $contactId,
                    ])->all()
                );
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => trans(':count contact(s) imported successfully.', ['count' => count($toInsert)]),
            'count'   => count($toInsert),
        ]);
    }
}
