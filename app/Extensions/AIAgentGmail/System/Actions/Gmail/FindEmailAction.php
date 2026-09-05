<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentGmail\System\Actions\Gmail;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\AIAgentGmail\System\Gmail\GmailClientFactory;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Log;

class FindEmailAction implements ActionInterface
{
    public function __construct(private readonly GmailClientFactory $factory) {}

    /**
     * Config keys:
     *   - query           (string) : Gmail search query (required)
     *   - max_results     (int)    : max messages (default: 10, max: 50)
     *   - return_format   (string) : id | subject | body | sender | date | full_as_string
     *   - store_output_as (string) : context key (default: found_emails)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'found_emails';
        $returnFormat = $config['return_format'] ?? 'id';

        Log::debug('FindEmailAction: start', [
            'workflow_id'     => $workflow->id,
            'user_id'         => $workflow->user_id,
            'store_output_as' => $storeOutputAs,
            'return_format'   => $returnFormat,
            'config'          => $config,
        ]);

        $gmail = $this->factory->make($workflow->user_id);
        $query = $this->interpolate($config['query'] ?? '', $context);
        $maxResults = min((int) ($config['max_results'] ?? 10), 50);

        $listResult = $gmail->users_messages->listUsersMessages('me', ['q' => $query, 'maxResults' => $maxResults]);
        $rawMessages = $listResult->getMessages() ?? [];

        if ($returnFormat === 'id') {
            $ids = array_map(fn ($m) => $m->getId(), $rawMessages);
            Log::debug('FindEmailAction: raw ids', ['ids' => $ids, 'types' => array_map('gettype', $ids)]);
        }

        $output = match ($returnFormat) {
            'id'             => implode(', ', array_map(fn ($m) => $m->getId(), $rawMessages)),
            'subject'        => $this->fetchField($gmail, $rawMessages, 'subject'),
            'sender'         => $this->fetchField($gmail, $rawMessages, 'sender'),
            'date'           => $this->fetchField($gmail, $rawMessages, 'date'),
            'body'           => $this->fetchBodies($gmail, $rawMessages),
            'full_as_string' => $this->fetchFullAsString($gmail, $rawMessages),
            default          => implode(', ', array_map(fn ($m) => $m->getId(), $rawMessages)),
        };

        Log::debug('FindEmailAction: result', [
            'workflow_id'     => $workflow->id,
            'store_output_as' => $storeOutputAs,
            'return_format'   => $returnFormat,
            'count'           => count($rawMessages),
            'output'          => $output,
        ]);

        return array_merge($context, [$storeOutputAs => $output]);
    }

    /** @param \Google\Service\Gmail\Message[] $rawMessages */
    private function fetchField(Gmail $gmail, array $rawMessages, string $field): string
    {
        $headerMap = ['subject' => 'subject', 'sender' => 'from', 'date' => 'date'];
        $header = $headerMap[$field] ?? $field;

        $values = [];

        foreach ($rawMessages as $raw) {
            $detail = $gmail->users_messages->get('me', $raw->getId(), ['format' => 'metadata', 'metadataHeaders' => [ucfirst($header), 'From', 'Date']]);
            $headers = collect($detail->getPayload()?->getHeaders() ?? [])->keyBy(fn ($h) => strtolower($h->getName()));
            $value = $headers->get($header)?->getValue() ?? '';

            if ($field === 'sender') {
                $value = $this->extractEmail($value);
            }

            $values[] = $value;
        }

        return implode("\n", $values);
    }

    /** @param \Google\Service\Gmail\Message[] $rawMessages */
    private function fetchBodies(Gmail $gmail, array $rawMessages): string
    {
        $bodies = [];

        foreach ($rawMessages as $raw) {
            $detail = $gmail->users_messages->get('me', $raw->getId(), ['format' => 'full']);
            $bodies[] = $this->extractBody($detail);
        }

        return implode("\n---\n", array_filter($bodies));
    }

    /** @param \Google\Service\Gmail\Message[] $rawMessages */
    private function fetchFullAsString(Gmail $gmail, array $rawMessages): string
    {
        $lines = [];
        $i = 1;

        foreach ($rawMessages as $raw) {
            $detail = $gmail->users_messages->get('me', $raw->getId(), ['format' => 'full']);
            $headers = collect($detail->getPayload()?->getHeaders() ?? [])->keyBy(fn ($h) => strtolower($h->getName()));
            $body = $this->extractBody($detail);

            $lines[] = implode("\n", array_filter([
                "{$i}.",
                'ID: ' . $detail->getId(),
                'Subject: ' . ($headers->get('subject')?->getValue() ?? ''),
                'From: ' . $this->extractEmail($headers->get('from')?->getValue() ?? ''),
                'Date: ' . ($headers->get('date')?->getValue() ?? ''),
                'Snippet: ' . ($detail->getSnippet() ?? ''),
                $body !== '' ? "Body:\n{$body}" : null,
            ]));

            $i++;
        }

        return implode("\n\n", $lines);
    }

    private function extractBody(Message $message): string
    {
        $payload = $message->getPayload();

        if (! $payload) {
            return '';
        }

        $data = $payload->getBody()?->getData();

        if ($data) {
            return base64_decode(strtr($data, '-_', '+/'));
        }

        return $this->findTextPart($payload->getParts() ?? []);
    }

    /** @param \Google\Service\Gmail\MessagePart[] $parts */
    private function findTextPart(array $parts): string
    {
        foreach ($parts as $part) {
            if ($part->getMimeType() === 'text/plain') {
                $data = $part->getBody()?->getData();

                if ($data) {
                    return base64_decode(strtr($data, '-_', '+/'));
                }
            }

            $nested = $this->findTextPart($part->getParts() ?? []);

            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }

    private function extractEmail(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            return $m[1];
        }

        return trim($from);
    }

    private function interpolate(string $template, array $context): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($context): string {
            $value = $context[$matches[1]] ?? $matches[0];

            if (is_array($value)) {
                return json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?: $matches[0];
            }

            return (string) $value;
        }, $template);
    }
}
