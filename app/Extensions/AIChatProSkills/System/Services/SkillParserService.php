<?php

declare(strict_types=1);

namespace App\Extensions\AIChatProSkills\System\Services;

use App\Extensions\AIChatProSkills\System\Models\Skill;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class SkillParserService
{
    /**
     * Parse an uploaded .zip or .skill file.
     *
     * @return array{name: string, description: string, instructions: string, bundled_resources: array|null}
     */
    public function parseSkillFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        // Detect zip archives by extension, MIME type, or magic bytes (PK header)
        $isZip = in_array($extension, ['zip', 'skill'])
            || $mime === 'application/zip'
            || $this->hasZipMagicBytes($file->getRealPath());

        if ($isZip) {
            return $this->parseZipFile($file);
        }

        // .md / .txt files — treat as raw SKILL.md content
        return Skill::parseSkillMd(file_get_contents($file->getRealPath()));
    }

    /**
     * Check if an uploaded file is a zip archive (by extension, MIME, or magic bytes).
     */
    public function isZipFile(UploadedFile $file): bool
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return in_array($ext, ['zip', 'skill'])
            || $file->getMimeType() === 'application/zip'
            || $this->hasZipMagicBytes($file->getRealPath());
    }

    /**
     * Check if a file starts with the ZIP magic bytes (PK\x03\x04).
     */
    private function hasZipMagicBytes(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if (! $handle) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        return $header === "PK\x03\x04";
    }

    /**
     * Parse a .zip file, extract SKILL.md from it.
     *
     * @return array{name: string, description: string, instructions: string, bundled_resources: array|null}
     */
    private function parseZipFile(UploadedFile $file): array
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException(__('Failed to open zip file.'));
        }

        $skillMdContent = null;
        $bundledResources = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $basename = basename($filename);

            // Find SKILL.md at root or one level deep
            if (strtoupper($basename) === 'SKILL.MD') {
                $skillMdContent = $zip->getFromIndex($i);
            } else {
                // Track other files as bundled resources
                $parts = explode('/', $filename);
                if (count($parts) >= 2 && ! str_ends_with($filename, '/')) {
                    $folder = $parts[0];
                    if (in_array($folder, ['scripts', 'references', 'assets'])) {
                        $bundledResources[$folder][] = $basename;
                    }
                }
            }
        }

        $zip->close();

        if ($skillMdContent === null) {
            throw new RuntimeException(__('No SKILL.md file found in the zip archive.'));
        }

        $parsed = Skill::parseSkillMd($skillMdContent);

        if (! empty($bundledResources)) {
            $parsed['bundled_resources'] = array_merge(
                $parsed['bundled_resources'] ?? [],
                $bundledResources
            );
        }

        return $parsed;
    }

    /**
     * Fetch and parse a SKILL.md from a public GitHub repository.
     *
     * @return array{name: string, description: string, instructions: string, bundled_resources: array|null, source_url: string}
     */
    /**
     * Fetch and parse a skill from a GitHub URL.
     * If URL points to a folder with SKILL.md, fetches the entire skill folder.
     * If URL points to a single .md file, fetches just that file.
     *
     * @return array{name: string, description: string, instructions: string, bundled_resources: array|null, source_url: string, files?: array}
     */
    public function parseGithubUrl(string $url): array
    {
        $parsed = $this->extractGithubOwnerRepo($url);
        $filePath = $parsed['path'];

        // If path points to a specific SKILL.md inside a folder, fetch the whole skill folder
        if ($filePath !== null && strtoupper(basename($filePath)) === 'SKILL.MD') {
            $skillFolder = dirname($filePath);

            if ($skillFolder !== '.') {
                $result = $this->fetchGithubSkillFolder($url, $skillFolder);
                $result['source_url'] = $url;

                return $result;
            }
        }

        // If path points to a folder (from /tree/ URL), fetch the skill folder
        if ($filePath !== null && str_ends_with($filePath, '/SKILL.md')) {
            $skillFolder = dirname($filePath);

            if ($skillFolder !== '.') {
                $result = $this->fetchGithubSkillFolder($url, $skillFolder);
                $result['source_url'] = $url;

                return $result;
            }
        }

        // If no path, scan for SKILL.md at repo root
        if ($filePath === null) {
            $filePath = 'SKILL.md';
        }

        // Fetch single file
        $apiUrl = "https://raw.githubusercontent.com/{$parsed['owner']}/{$parsed['repo']}/{$parsed['branch']}/{$filePath}";

        $context = stream_context_create([
            'http' => [
                'header'          => "User-Agent: MagicAI\r\n",
                'timeout'         => 10,
                'follow_location' => true,
            ],
        ]);

        $content = @file_get_contents($apiUrl, false, $context);

        if ($content === false) {
            throw new RuntimeException(__('Could not fetch SKILL.md from the repository. Make sure it exists at the specified path.'));
        }

        $result = Skill::parseSkillMd($content);
        $result['source_url'] = $url;

        return $result;
    }

    /**
     * Extract owner, repo, branch, and optional file path from a GitHub URL.
     *
     * @return array{owner: string, repo: string, branch: string, path: string|null}
     */
    private function extractGithubOwnerRepo(string $url): array
    {
        // Match: github.com/owner/repo/blob/branch/path/to/SKILL.md
        if (preg_match('/github\.com\/([^\/]+)\/([^\/]+)\/blob\/([^\/]+)\/(.+)/', $url, $matches)) {
            return [
                'owner'  => $matches[1],
                'repo'   => rtrim($matches[2], '.git'),
                'branch' => $matches[3],
                'path'   => $matches[4],
            ];
        }

        // Match: github.com/owner/repo/tree/branch
        if (preg_match('/github\.com\/([^\/]+)\/([^\/]+)\/tree\/([^\/\s?]+)(?:\/(.+))?/', $url, $matches)) {
            return [
                'owner'  => $matches[1],
                'repo'   => rtrim($matches[2], '.git'),
                'branch' => $matches[3],
                'path'   => isset($matches[4]) ? rtrim($matches[4], '/') . '/SKILL.md' : null,
            ];
        }

        // Match: github.com/owner/repo
        if (preg_match('/github\.com\/([^\/]+)\/([^\/\s?]+)/', $url, $matches)) {
            return [
                'owner'  => $matches[1],
                'repo'   => rtrim($matches[2], '.git'),
                'branch' => 'main',
                'path'   => null,
            ];
        }

        throw new RuntimeException(__('Invalid GitHub URL format.'));
    }

    /**
     * Scan a GitHub repo for skill folders (containing SKILL.md).
     * Returns a list of skill folders with their file counts.
     *
     * @return array<int, array{path: string, name: string, folder: string, file_count: int}>
     */
    public function scanGithubRepo(string $url): array
    {
        $tree = $this->fetchGithubTree($url);

        // Find all SKILL.md files to identify skill folders
        $skillFolders = [];
        $allPaths = [];

        foreach ($tree as $item) {
            $allPaths[] = $item['path'];

            if ($item['type'] !== 'blob') {
                continue;
            }

            if (strtoupper(basename($item['path'])) === 'SKILL.MD') {
                $dir = dirname($item['path']);
                $skillName = basename($dir);

                // Skip root-level SKILL.md or template SKILL.md
                if ($dir === '.' || $skillName === 'template') {
                    continue;
                }

                $skillFolders[$dir] = [
                    'path'       => $item['path'],
                    'name'       => $skillName,
                    'folder'     => $dir,
                    'file_count' => 0,
                ];
            }
        }

        // Count files per skill folder
        foreach ($allPaths as $filePath) {
            foreach ($skillFolders as $dir => &$skill) {
                if (str_starts_with($filePath, $dir . '/') && $filePath !== $skill['path']) {
                    $skill['file_count']++;
                }
            }
        }

        return array_values($skillFolders);
    }

    /**
     * Fetch the full GitHub tree for a repository.
     *
     * @return array<int, array{path: string, type: string}>
     */
    private function fetchGithubTree(string $url): array
    {
        $parsed = $this->extractGithubOwnerRepo($url);

        $apiUrl = "https://api.github.com/repos/{$parsed['owner']}/{$parsed['repo']}/git/trees/{$parsed['branch']}?recursive=1";

        $context = stream_context_create([
            'http' => [
                'header'          => "User-Agent: MagicAI\r\nAccept: application/vnd.github.v3+json\r\n",
                'timeout'         => 15,
                'follow_location' => true,
            ],
        ]);

        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            throw new RuntimeException(__('Could not access the repository. Make sure it is public and the URL is correct.'));
        }

        $data = json_decode($response, true);

        if (! isset($data['tree']) || ! is_array($data['tree'])) {
            throw new RuntimeException(__('Unexpected response from GitHub API.'));
        }

        return $data['tree'];
    }

    /**
     * Fetch and parse a skill folder from GitHub, including all bundled resources.
     *
     * @return array{name: string, description: string, instructions: string, bundled_resources: array|null, files: array}
     */
    public function fetchGithubSkillFolder(string $url, string $skillFolder): array
    {
        $parsed = $this->extractGithubOwnerRepo($url);
        $tree = $this->fetchGithubTree($url);

        $prefix = rtrim($skillFolder, '/') . '/';
        $files = [];

        // Collect all file paths in this skill folder
        $filePaths = [];
        foreach ($tree as $item) {
            if ($item['type'] === 'blob' && str_starts_with($item['path'], $prefix)) {
                $filePaths[] = $item['path'];
            }
        }

        $context = stream_context_create([
            'http' => [
                'header'          => "User-Agent: MagicAI\r\n",
                'timeout'         => 10,
                'follow_location' => true,
            ],
        ]);

        // Fetch each file's content
        foreach ($filePaths as $filePath) {
            $rawUrl = "https://raw.githubusercontent.com/{$parsed['owner']}/{$parsed['repo']}/{$parsed['branch']}/{$filePath}";
            $content = @file_get_contents($rawUrl, false, $context);

            if ($content === false) {
                continue;
            }

            // Get relative path within the skill folder
            $relativePath = Str::after($filePath, $prefix);

            $files[] = [
                'path'    => $relativePath,
                'content' => $content,
            ];
        }

        // Find SKILL.md and parse it
        $skillMdContent = null;
        foreach ($files as $file) {
            if (strtoupper(basename($file['path'])) === 'SKILL.MD' && dirname($file['path']) === '.') {
                $skillMdContent = $file['content'];

                break;
            }
        }

        // Fallback: any SKILL.md
        if ($skillMdContent === null) {
            foreach ($files as $file) {
                if (strtoupper(basename($file['path'])) === 'SKILL.MD') {
                    $skillMdContent = $file['content'];

                    break;
                }
            }
        }

        if ($skillMdContent === null) {
            throw new RuntimeException(__('No SKILL.md found in the skill folder.'));
        }

        $parsed = Skill::parseSkillMd($skillMdContent);
        $parsed['files'] = $files;

        return $parsed;
    }

    /**
     * Fetch and parse a specific file from a GitHub repo by path.
     * Used as fallback for single-file imports.
     *
     * @return array{name: string, description: string, instructions: string, bundled_resources: array|null}
     */
    public function fetchGithubFile(string $url, string $filePath): array
    {
        $parsed = $this->extractGithubOwnerRepo($url);

        $rawUrl = "https://raw.githubusercontent.com/{$parsed['owner']}/{$parsed['repo']}/{$parsed['branch']}/{$filePath}";

        $context = stream_context_create([
            'http' => [
                'header'          => "User-Agent: MagicAI\r\n",
                'timeout'         => 10,
                'follow_location' => true,
            ],
        ]);

        $content = @file_get_contents($rawUrl, false, $context);

        if ($content === false) {
            throw new RuntimeException(__('Could not fetch :path from the repository.', ['path' => $filePath]));
        }

        return Skill::parseSkillMd($content);
    }

    /**
     * Extract and store all skill files from a zip to disk.
     *
     * @return array<string, array<int, array{name: string, path: string, size: int}>>
     */
    public function extractAndStoreZipFiles(UploadedFile $file, ?int $userId, string $slug): array
    {
        $zip = new ZipArchive;

        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException(__('Failed to open zip file.'));
        }

        $owner = $userId ?? 'public';
        $basePath = storage_path("app/skills/{$owner}/{$slug}");

        File::ensureDirectoryExists($basePath);

        $bundledResources = [];
        $skillMdPrefix = null;

        // First pass: find SKILL.md to detect prefix (e.g., "skill-name/SKILL.md")
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            if (strtoupper(basename($filename)) === 'SKILL.MD') {
                $dir = dirname($filename);
                $skillMdPrefix = ($dir === '.') ? '' : $dir . '/';

                break;
            }
        }

        if ($skillMdPrefix === null) {
            $zip->close();

            throw new RuntimeException(__('No SKILL.md file found in the zip archive.'));
        }

        // Second pass: extract all files
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Skip directories
            if (str_ends_with($filename, '/')) {
                continue;
            }

            // Get relative path by stripping prefix
            $relativePath = $skillMdPrefix !== '' ? Str::after($filename, $skillMdPrefix) : $filename;

            if ($relativePath === '' || $relativePath === $filename && $skillMdPrefix !== '') {
                continue;
            }

            // Security: validate file path
            if (! $this->isSecureFilePath($relativePath)) {
                continue;
            }

            $content = $zip->getFromIndex($i);

            if ($content === false) {
                continue;
            }

            // Security: validate file size (2MB per file)
            if (! $this->isSecureFileSize($content)) {
                continue;
            }

            $targetPath = $basePath . '/' . $relativePath;
            File::ensureDirectoryExists(dirname($targetPath));
            File::put($targetPath, $content);

            // Track bundled resources (not SKILL.md itself)
            if (strtoupper(basename($relativePath)) !== 'SKILL.MD') {
                $parts = explode('/', $relativePath);
                $folder = $parts[0];

                $bundledResources[$folder][] = [
                    'name' => basename($relativePath),
                    'path' => $relativePath,
                    'size' => strlen($content),
                ];
            }
        }

        $zip->close();

        return $bundledResources;
    }

    /**
     * Store a SKILL.md-only skill (from .md upload or content creation).
     */
    public function storeSkillMdFile(string $content, ?int $userId, string $slug): void
    {
        $owner = $userId ?? 'public';
        $basePath = storage_path("app/skills/{$owner}/{$slug}");

        File::ensureDirectoryExists($basePath);
        File::put($basePath . '/SKILL.md', $content);
    }

    /**
     * Store multiple skill files from parsed content (used by create-from-content).
     *
     * @param  array<int, array{path: string, content: string}>  $files
     *
     * @return array<string, array<int, array{name: string, path: string, size: int}>>
     */
    public function storeSkillFiles(array $files, ?int $userId, string $slug): array
    {
        $owner = $userId ?? 'public';
        $basePath = storage_path("app/skills/{$owner}/{$slug}");

        File::ensureDirectoryExists($basePath);

        $bundledResources = [];

        foreach ($files as $file) {
            $relativePath = $file['path'];
            $content = $file['content'];

            // Security: validate file path and size
            if (! $this->isSecureFilePath($relativePath) || ! $this->isSecureFileSize($content)) {
                continue;
            }

            $targetPath = $basePath . '/' . $relativePath;
            File::ensureDirectoryExists(dirname($targetPath));
            File::put($targetPath, $content);

            if (strtoupper(basename($relativePath)) !== 'SKILL.MD') {
                $parts = explode('/', $relativePath);
                $folder = $parts[0] ?? 'other';

                $bundledResources[$folder][] = [
                    'name' => basename($relativePath),
                    'path' => $relativePath,
                    'size' => strlen($content),
                ];
            }
        }

        return $bundledResources;
    }

    /**
     * Validate the parsed skill data has required fields.
     */
    public function validateSchema(array $parsed): bool
    {
        return ! empty($parsed['name']) && ! empty($parsed['instructions']);
    }

    /**
     * Sanitize instructions to prevent prompt injection patterns.
     */
    public function sanitizeInstructions(string $instructions): string
    {
        // Strip HTML/script tags
        $instructions = strip_tags($instructions);

        // Limit length
        $instructions = Str::limit($instructions, 50000, '');

        return $instructions;
    }

    /**
     * Validate a file path for security — prevent path traversal, block dangerous extensions.
     */
    public function isSecureFilePath(string $path): bool
    {
        // Block path traversal and null bytes
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            return false;
        }

        // Must be relative (no absolute paths)
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return false;
        }

        // Block dangerous/executable file extensions
        $dangerousExts = [
            'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'vbs', 'vbe',
            'wsf', 'wsh', 'ps1', 'psm1', 'msi', 'msp', 'jar', 'war',
            'dll', 'so', 'dylib', 'bin', 'elf', 'deb', 'rpm',
            'htaccess', 'htpasswd', 'env', 'pem', 'key', 'crt',
        ];

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, $dangerousExts)) {
            return false;
        }

        // Block hidden files/folders (starting with .)
        foreach (explode('/', $path) as $segment) {
            if ($segment !== '' && str_starts_with($segment, '.')) {
                return false;
            }
        }

        // Limit directory depth to prevent deeply nested paths
        if (substr_count($path, '/') > 5) {
            return false;
        }

        return true;
    }

    /**
     * Validate file content size — enforce per-file limit.
     */
    public function isSecureFileSize(string $content, int $maxBytes = 2097152): bool
    {
        return strlen($content) <= $maxBytes; // Default 2MB per file
    }
}
