<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Parsers;

use App\Extensions\PhoneCallAgent\System\Enums\TrainTypeEnum;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgentTrain;
use Illuminate\Support\Facades\Auth;

class LinkParser
{
    private string $baseUrl;

    private array $links = [];

    private int $maxLinks = 30;

    private array $invalidPaths = ['/cdn-cgi/'];

    private array $contents = [];

    public function insertEmbeddings(ExtPhoneCallAgent $agent): void
    {
        foreach ($this->contents as $url => $data) {
            ExtPhoneCallAgentTrain::query()
                ->firstOrCreate([
                    'type'     => TrainTypeEnum::url,
                    'user_id'  => Auth::id(),
                    'agent_id' => $agent->getKey(),
                    'url'      => $url,
                ], [
                    'name' => $data['title'],
                ]);
        }
    }

    public function crawl(bool $single = false): static
    {
        $this->crawlPage($this->baseUrl, $single);

        return $this;
    }

    private function crawlPage(string $url, bool $single): void
    {
        ini_set('max_execution_time', -1);

        $html = @file_get_contents($url);
        if ($html === false) {
            return;
        }

        preg_match('/<title>(.*?)<\/title>/si', $html, $titleMatch);
        $title = $titleMatch[1] ?? 'Untitled';

        $this->contents[$url] = [
            'title' => $title,
        ];

        if ($single) {
            return;
        }

        preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)"/', $html, $matches);
        foreach ($matches[1] as $link) {
            $absoluteLink = $this->makeAbsoluteUrl($link);
            if ($this->isValidLink($absoluteLink)) {
                $this->links[] = $absoluteLink;
                if (count($this->links) >= $this->maxLinks) {
                    return;
                }
                $this->crawlPage($absoluteLink, false);
            }
        }
    }

    private function makeAbsoluteUrl(string $url): ?string
    {
        if (str_starts_with($url, 'http') || str_starts_with($url, 'https')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return parse_url($this->baseUrl, PHP_URL_SCHEME) . '://' . parse_url($this->baseUrl, PHP_URL_HOST) . $url;
        }

        return null;
    }

    private function isValidLink(?string $url): bool
    {
        return $url &&
            ! in_array($url, $this->links, true) &&
            $this->isSameDomain($url, $this->baseUrl) &&
            ! $this->hasInvalidPath($url) &&
            ! $this->isImage($url);
    }

    private function isSameDomain(string $url1, string $url2): bool
    {
        return parse_url($url1, PHP_URL_HOST) === parse_url($url2, PHP_URL_HOST);
    }

    private function hasInvalidPath(string $url): bool
    {
        return (bool) array_filter($this->invalidPaths, fn ($invalidPath) => str_contains($url, $invalidPath));
    }

    private function isImage(string $url): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'apng', 'avif', 'svg', 'webp', 'ico', 'tiff'];
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));

        return in_array($extension, $imageExtensions, true);
    }

    public function getContents(): array
    {
        return $this->contents;
    }

    public function setBaseUrl(string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }
}
