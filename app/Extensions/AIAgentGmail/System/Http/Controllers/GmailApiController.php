<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Http\Controllers;

use App\Extensions\AIAgentGmail\System\Gmail\GmailClientFactory;
use App\Http\Controllers\Controller;
use Google\Service\Gmail\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class GmailApiController extends Controller
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    public function messages(Request $request): JsonResponse
    {
        try {
            $gmail = $this->factory->make(Auth::id());
            $query = $request->string('q', '')->toString();

            $listParams = ['maxResults' => 20];

            if ($query !== '') {
                $listParams['q'] = $query;
            }

            $result = $gmail->users_messages->listUsersMessages('me', $listParams);
            $messages = $result->getMessages() ?? [];

            $items = [];

            foreach ($messages as $msg) {
                $detail = $gmail->users_messages->get('me', $msg->getId(), [
                    'format'          => 'metadata',
                    'metadataHeaders' => ['Subject', 'From', 'Date'],
                ]);
                $items[] = $this->formatMessage($detail);
            }

            return response()->json(['data' => $items]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function threads(Request $request): JsonResponse
    {
        try {
            $gmail = $this->factory->make(Auth::id());
            $query = $request->string('q', '')->toString();

            $listParams = ['maxResults' => 20];

            if ($query !== '') {
                $listParams['q'] = $query;
            }

            $result = $gmail->users_threads->listUsersThreads('me', $listParams);
            $threads = $result->getThreads() ?? [];

            $items = [];

            foreach ($threads as $thread) {
                $detail = $gmail->users_threads->get('me', $thread->getId(), ['format' => 'metadata', 'metadataHeaders' => ['Subject', 'From', 'Date']]);
                $firstMsg = $detail->getMessages()[0] ?? null;
                $items[] = $firstMsg
                    ? array_merge($this->formatMessage($firstMsg), ['id' => $thread->getId()])
                    : ['id' => $thread->getId(), 'subject' => '(no subject)', 'from' => '', 'date' => ''];
            }

            return response()->json(['data' => $items]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function formatMessage(Message $message): array
    {
        $headers = collect($message->getPayload()?->getHeaders() ?? [])
            ->keyBy(fn ($h) => strtolower($h->getName()));

        return [
            'id'      => $message->getId(),
            'subject' => $headers->get('subject')?->getValue() ?? '(no subject)',
            'from'    => $headers->get('from')?->getValue() ?? '',
            'date'    => $headers->get('date')?->getValue() ?? '',
        ];
    }
}
