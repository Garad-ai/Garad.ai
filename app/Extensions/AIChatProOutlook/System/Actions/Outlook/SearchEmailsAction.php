<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProOutlook\System\Actions\Outlook;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProOutlook\System\Graph\GraphClientFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

class SearchEmailsAction
{
    public function __construct(private readonly GraphClientFactory $factory) {}

    /**
     * @param  array{query?: string, max_results?: int}  $arguments
     */
    public function execute(array $arguments, AIChatProConnector $connector): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $maxResults = max(1, min((int) ($arguments['max_results'] ?? 5), 20));

        $allowedFolders = $connector->selectedAccessFor('folders');

        // Over-fetch when post-filtering by folder so we can still return $maxResults after the in-PHP filter.
        $fetchTop = $allowedFolders !== null ? min($maxResults * 5, 50) : $maxResults;

        $params = [
            '$top'     => $fetchTop,
            '$select'  => 'id,subject,from,receivedDateTime,bodyPreview,parentFolderId',
            '$orderby' => 'receivedDateTime desc',
        ];

        // Graph's `parentFolderId in (...)` filter is unreliable, so we post-filter folders in PHP below.
        if ($connector->selectedRecentOnly()) {
            $params['$filter'] = 'receivedDateTime ge ' . now()->subDays(30)->toIso8601ZuluString();
        }

        if ($query !== '') {
            // $search is mutually exclusive with $filter and $orderby in Graph;
            // post-filter by parent folder / recency below where necessary.
            unset($params['$filter'], $params['$orderby']);
            $params['$search'] = '"' . str_replace('"', '\\"', $query) . '"';
        }

        try {
            $client = $this->factory->make($connector);
            $response = $client
                ->withHeaders($query !== '' ? ['ConsistencyLevel' => 'eventual'] : [])
                ->get('/me/messages', $params);
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }

        if (! $response->successful()) {
            Log::warning('[connectors] Outlook search_emails request failed', [
                'connector_id' => $connector->id,
                'status'       => $response->status(),
                'params'       => $params,
                'body'         => $response->json() ?? $response->body(),
            ]);

            $graphMessage = (string) data_get($response->json(), 'error.message', '');

            return [
                'error' => 'Outlook request failed: ' . $response->status()
                    . ($graphMessage !== '' ? ' - ' . $graphMessage : ''),
            ];
        }

        $rawMessages = (array) data_get($response->json(), 'value', []);
        $messages = [];

        foreach ($rawMessages as $raw) {
            $parentFolderId = (string) data_get($raw, 'parentFolderId', '');

            if ($allowedFolders !== null && $parentFolderId !== '' && ! in_array($parentFolderId, $allowedFolders, true)) {
                continue;
            }

            if ($connector->selectedRecentOnly()) {
                $received = (string) data_get($raw, 'receivedDateTime', '');
                if ($received !== '' && strtotime($received) < now()->subDays(30)->getTimestamp()) {
                    continue;
                }
            }

            $messages[] = [
                'id'            => (string) data_get($raw, 'id', ''),
                'subject'       => (string) data_get($raw, 'subject', ''),
                'from'          => (string) (data_get($raw, 'from.emailAddress.address') ?? data_get($raw, 'from.emailAddress.name', '')),
                'received_date' => (string) data_get($raw, 'receivedDateTime', ''),
                'body_preview'  => (string) data_get($raw, 'bodyPreview', ''),
            ];

            if (count($messages) >= $maxResults) {
                break;
            }
        }

        return [
            'query'    => $query,
            'count'    => count($messages),
            'messages' => $messages,
        ];
    }
}
