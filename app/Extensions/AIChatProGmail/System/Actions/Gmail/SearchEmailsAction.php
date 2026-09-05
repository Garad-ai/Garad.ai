<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProGmail\System\Actions\Gmail;

use App\Extensions\AIChatPro\System\Connectors\Models\AIChatProConnector;
use App\Extensions\AIChatProGmail\System\Gmail\GmailClientFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

class SearchEmailsAction
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * @param  array{query?: string, max_results?: int, label?: string}  $arguments
     */
    public function execute(array $arguments, AIChatProConnector $connector): array
    {
        $query = (string) ($arguments['query'] ?? '');
        $maxResults = max(1, min((int) ($arguments['max_results'] ?? 5), 20));
        $label = $arguments['label'] ?? null;

        $allowedLabels = $connector->selectedAccessFor('labels');

        // Gmail's `labelIds` parameter is AND-combined. To express "messages in
        // ANY of the selected labels" we fan out one list call per label and
        // merge the results. A single label (or the AI narrowing to one of the
        // allowed labels) stays as a single call.
        $labelsToQuery = [null];
        if ($allowedLabels !== null) {
            $labelsToQuery = ($label && in_array($label, $allowedLabels, true))
                ? [$label]
                : $allowedLabels;
        } elseif ($label) {
            $labelsToQuery = [$label];
        }

        if ($connector->selectedRecentOnly()) {
            $query = trim($query . ' newer_than:30d');
        }

        try {
            $gmail = $this->factory->make($connector);
        } catch (Throwable $exception) {
            Log::error('[connectors] Gmail SearchEmailsAction: client init threw', [
                'message' => $exception->getMessage(),
            ]);

            return ['error' => $exception->getMessage()];
        }

        $rawMessages = [];
        $seenIds = [];

        foreach ($labelsToQuery as $singleLabel) {
            $params = ['maxResults' => $maxResults];
            if ($singleLabel !== null) {
                $params['labelIds'] = [$singleLabel];
            }
            if ($query !== '') {
                $params['q'] = $query;
            }

            try {
                $listResult = $gmail->users_messages->listUsersMessages('me', $params);
            } catch (Throwable $exception) {
                Log::error('[connectors] Gmail SearchEmailsAction: Gmail API threw', [
                    'message' => $exception->getMessage(),
                    'label'   => $singleLabel,
                ]);

                return ['error' => $exception->getMessage()];
            }

            foreach ($listResult->getMessages() ?? [] as $message) {
                $id = (string) $message->getId();
                if ($id === '' || isset($seenIds[$id])) {
                    continue;
                }
                $seenIds[$id] = true;
                $rawMessages[] = $message;

                if (count($rawMessages) >= $maxResults) {
                    break 2;
                }
            }
        }

        $messages = [];

        foreach ($rawMessages as $raw) {
            try {
                $detail = $gmail->users_messages->get('me', $raw->getId(), [
                    'format'          => 'metadata',
                    'metadataHeaders' => ['Subject', 'From', 'Date'],
                ]);
            } catch (Throwable) {
                continue;
            }

            $headers = collect($detail->getPayload()?->getHeaders() ?? [])
                ->keyBy(fn ($h) => strtolower($h->getName()));

            $messages[] = [
                'id'      => $detail->getId(),
                'subject' => $headers->get('subject')?->getValue() ?? '',
                'from'    => $headers->get('from')?->getValue() ?? '',
                'date'    => $headers->get('date')?->getValue() ?? '',
                'snippet' => $detail->getSnippet() ?? '',
            ];
        }

        return [
            'query'    => $query,
            'count'    => count($messages),
            'messages' => $messages,
        ];
    }
}
