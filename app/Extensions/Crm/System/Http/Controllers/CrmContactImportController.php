<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Http\Controllers;

use App\Extensions\Crm\System\Http\Requests\CrmContactImportRequest;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Services\CrmContactImportService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CrmContactImportController extends Controller
{
    public function __construct(private readonly CrmContactImportService $importService) {}

    public function headers(CrmContactImportRequest $request): JsonResponse
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

        $headers = $this->importService->readCsvHeaders($request->file('file'));

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

    public function preview(CrmContactImportRequest $request): JsonResponse
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

        $result = $this->importService->parseContacts(
            $request->file('file'),
            Auth::id(),
            $request->input('mapping', []),
            $request->input('default_status', 'active'),
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

    public function import(CrmContactImportRequest $request): JsonResponse
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

        $userId = Auth::id();

        $result = $this->importService->parseContacts(
            $request->file('file'),
            $userId,
            $request->input('mapping', []),
            $request->input('default_status', 'active'),
        );

        if (isset($result['error'])) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['error'],
            ]);
        }

        $validRows = $result['valid'];

        $include = $request->input('include_indices');
        if (is_array($include) && $include !== []) {
            $validRows = array_values(array_intersect_key($validRows, array_flip($include)));
        }

        if (empty($validRows)) {
            return response()->json([
                'status'  => 'error',
                'message' => trans('No valid contacts found in the CSV file.'),
            ]);
        }

        $now = now();

        $toInsert = array_map(static fn (array $row) => [
            'user_id'        => $userId,
            'first_name'     => $row['first_name'],
            'last_name'      => $row['last_name'],
            'email'          => $row['email'],
            'phone'          => $row['phone'],
            'job_title'      => $row['job_title'],
            'crm_company_id' => $row['crm_company_id'],
            'status'         => $row['status'],
            'created_at'     => $now,
            'updated_at'     => $now,
        ], $validRows);

        CrmContact::query()->insert($toInsert);

        return response()->json([
            'status'  => 'success',
            'message' => trans(':count contact(s) imported successfully.', ['count' => count($toInsert)]),
            'count'   => count($toInsert),
        ]);
    }
}
