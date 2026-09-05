<?php

namespace App\Extensions\SocialMediaAgent\System\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class WebScrapingService
{
    protected Client $client;

    protected int $maxPages = 3;

    protected int $timeout = 10;

    public function __construct()
    {
        $this->client = new Client([
            'timeout'         => $this->timeout,
            'allow_redirects' => true,
            'verify'          => false, // For SSL issues
            'headers'         => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ],
        ]);
    }

    /**
     * Scrape a website and extract content from multiple pages
     */
    public function scrapeWebsite(string $url): array
    {
        try {
            $baseUrl = $this->getBaseUrl($url);
            $scrapedPages = [];

            // Scrape the main page
            $mainPageContent = $this->scrapePage($url);
            if ($mainPageContent) {
                $scrapedPages[] = $mainPageContent;

                // Extract internal links from main page
                $internalLinks = $this->extractInternalLinks($url, $mainPageContent['html'], $baseUrl);

                // Scrape additional pages (limit to maxPages - 1, since we already have the main page)
                $additionalPages = array_slice($internalLinks, 0, $this->maxPages - 1);

                foreach ($additionalPages as $link) {
                    $pageContent = $this->scrapePage($link);
                    if ($pageContent) {
                        $scrapedPages[] = $pageContent;
                    }
                }
            }

            return [
                'success'      => true,
                'base_url'     => $baseUrl,
                'pages_count'  => count($scrapedPages),
                'pages'        => $scrapedPages,
                'summary'      => $this->generateSummary($scrapedPages),
            ];
        } catch (Exception $e) {
            Log::error('WebScrapingService Error: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'pages'   => [],
            ];
        }
    }

    /**
     * Scrape a single page and extract relevant content
     */
    protected function scrapePage(string $url): ?array
    {
        try {
            $response = $this->client->get($url);
            $html = (string) $response->getBody();

            $crawler = new Crawler($html);

            // Extract title
            $title = $crawler->filter('title')->count() > 0
                ? $crawler->filter('title')->text()
                : '';

            // Extract meta description
            $metaDescription = $crawler->filter('meta[name="description"]')->count() > 0
                ? $crawler->filter('meta[name="description"]')->attr('content')
                : '';

            // Extract main content (try multiple selectors)
            $mainContent = $this->extractMainContent($crawler);

            // Extract headings
            $headings = $this->extractHeadings($crawler);

            return [
                'url'              => $url,
                'title'            => trim($title),
                'meta_description' => trim($metaDescription),
                'headings'         => $headings,
                'content'          => $mainContent,
                'html'             => $html, // Store for link extraction
            ];
        } catch (GuzzleException $e) {
            Log::warning("Failed to scrape page: {$url} - " . $e->getMessage());

            return null;
        }
    }

    /**
     * Extract main content from the page
     */
    protected function extractMainContent(Crawler $crawler): string
    {
        $selectors = [
            'main',
            'article',
            '[role="main"]',
            '.content',
            '#content',
            '.main-content',
            '#main-content',
            'body',
        ];

        foreach ($selectors as $selector) {
            try {
                if ($crawler->filter($selector)->count() > 0) {
                    $content = $crawler->filter($selector)->first()->text();

                    // Clean up the content
                    $content = preg_replace('/\s+/', ' ', $content);
                    $content = trim($content);

                    if (strlen($content) > 100) { // Minimum content length
                        return substr($content, 0, 5000); // Limit to 5000 chars
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return '';
    }

    /**
     * Extract all headings (h1, h2, h3) from the page
     */
    protected function extractHeadings(Crawler $crawler): array
    {
        $headings = [];

        try {
            $crawler->filter('h1, h2, h3')->each(function (Crawler $node) use (&$headings) {
                $text = trim($node->text());
                if (! empty($text)) {
                    $headings[] = $text;
                }
            });
        } catch (Exception $e) {
            // Ignore errors
        }

        return array_slice($headings, 0, 20); // Limit to 20 headings
    }

    /**
     * Extract internal links from HTML
     */
    protected function extractInternalLinks(string $currentUrl, string $html, string $baseUrl): array
    {
        $crawler = new Crawler($html, $currentUrl);
        $links = [];

        try {
            $crawler->filter('a[href]')->each(function (Crawler $node) use (&$links, $baseUrl, $currentUrl) {
                $href = $node->attr('href');

                // Convert relative URLs to absolute
                $absoluteUrl = $this->makeAbsoluteUrl($href, $currentUrl);

                // Only include internal links from the same domain
                if ($this->isInternalLink($absoluteUrl, $baseUrl)) {
                    $links[] = $absoluteUrl;
                }
            });
        } catch (Exception $e) {
            // Ignore errors
        }

        // Remove duplicates and return
        return array_values(array_unique($links));
    }

    /**
     * Convert relative URL to absolute URL
     */
    protected function makeAbsoluteUrl(string $url, string $baseUrl): string
    {
        // Already absolute
        if (parse_url($url, PHP_URL_SCHEME) !== null) {
            return $url;
        }

        $base = parse_url($baseUrl);

        // Handle protocol-relative URLs
        if (str_starts_with($url, '//')) {
            return ($base['scheme'] ?? 'http') . ':' . $url;
        }

        $baseScheme = $base['scheme'] ?? 'http';
        $baseHost = $base['host'] ?? '';
        $basePath = $base['path'] ?? '/';

        // Handle root-relative URLs
        if (str_starts_with($url, '/')) {
            return "{$baseScheme}://{$baseHost}{$url}";
        }

        // Handle relative URLs
        $basePath = substr($basePath, 0, strrpos($basePath, '/') + 1);

        return "{$baseScheme}://{$baseHost}{$basePath}{$url}";
    }

    /**
     * Check if a URL is internal (same domain)
     */
    protected function isInternalLink(string $url, string $baseUrl): bool
    {
        $urlHost = parse_url($url, PHP_URL_HOST);
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);

        return $urlHost === $baseHost;
    }

    /**
     * Get base URL (scheme + host)
     */
    protected function getBaseUrl(string $url): string
    {
        $parsed = parse_url($url);

        return ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
    }

    /**
     * Generate a summary from scraped pages
     */
    protected function generateSummary(array $pages): string
    {
        $allContent = [];

        foreach ($pages as $page) {
            if (! empty($page['meta_description'])) {
                $allContent[] = $page['meta_description'];
            }
            if (! empty($page['title'])) {
                $allContent[] = $page['title'];
            }
        }

        return implode(' | ', array_slice($allContent, 0, 5));
    }

    /**
     * Set maximum pages to scrape
     */
    public function setMaxPages(int $maxPages): self
    {
        $this->maxPages = $maxPages;

        return $this;
    }

    /**
     * Set request timeout
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }
}
