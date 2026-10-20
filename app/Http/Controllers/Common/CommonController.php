<?php

namespace App\Http\Controllers\Common;

use App\Helpers\Classes\Helper;
use App\Helpers\Classes\Localization;
use App\Helpers\Classes\MarketplaceHelper;
use App\Http\Controllers\Controller;
use App\Models\SettingTwo;
use Elseyyid\LaravelJsonLocationsManager\Models\Strings;
use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class CommonController extends Controller
{
    public function regenerate()
    {
        Artisan::call('elseyyid:location:install');

        return redirect()->route('elseyyid.translations.home')->with(config('elseyyid-location.message_flash_variable'), __('Language files regenerated!'));
    }

    public function debug()
    {
        $currentDebugValue = env('APP_DEBUG', false);

        $newDebugValue = ! $currentDebugValue;

        $envContent = file_get_contents(base_path('.env'));

        $envContent = preg_replace('/^APP_DEBUG=.*/m', 'APP_DEBUG=' . ($newDebugValue ? 'true' : 'false'), $envContent);

        file_put_contents(base_path('.env'), $envContent);

        Artisan::call('config:clear');

        return redirect()->back()->with('message', 'Debug mode updated successfully.');
    }

    public function setLocale(Request $request): RedirectResponse
    {
        $settings_two = SettingTwo::getCache();
        $settings_two->languages_default = $request->setLocale;
        $settings_two->save();
        Localization::setLocale($request->setLocale);

        return redirect()->route('elseyyid.translations.home', [$request->setLocale])->with(config('elseyyid-location.message_flash_variable'), $request->setLocale);
    }

    /**
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function translationsLangUpdateAll(Request $request)
    {
        ini_set('max_input_vars', -1);
        $json = json_decode($request->input('json'), true, 512, JSON_THROW_ON_ERROR);
        $column_name = $request->get('lang');
        foreach ($json as $code => $column_value) {
            if (! empty($column_value)) {
                $test = Strings::where('code', '=', $code)->update([$column_name => $column_value]);
            }
        }

        $lang = $column_name;
        $list = Strings::pluck($lang, 'en');

        $new_json = json_encode_prettify($list);
        $filesystem = new Filesystem;
        $filesystem->put(base_path('lang/' . $lang . '.json'), $new_json);

        if ($column_name == 'edit') {
            // Read existing values from en.json
            $enJsonPath = base_path('lang/en.json');
            $existingJson = $filesystem->get($enJsonPath);
            $existingValues = json_decode($existingJson, true);
            // Read non-empty values from edit.json
            $editJsonPath = base_path('lang/edit.json');
            $editJson = $filesystem->get($editJsonPath);
            $editValues = json_decode($editJson, true);
            // Update values in en.json using keys from edit.json
            foreach ($editValues as $key => $column_value) {
                // Check if the value is not empty
                if (! empty($column_value)) {
                    // Update the existing values with non-empty values using the key from edit.json
                    $existingValues[$key] = $column_value;
                }
            }
            // Convert the updated values to JSON
            $updatedJson = json_encode_prettify($existingValues);
            // Write the updated JSON to en.json
            $filesystem->put($enJsonPath, $updatedJson);
        }

        return response()->json(['code' => 200], 200);
    }

    public function translationsLangSave(Request $request)
    {
        if (Helper::appIsDemo()) {
            return response()->json(['code' => 403, 'message' => __('This feature is disabled in demo mode.')], 403);
        }

        $settings_two = SettingTwo::getCache();
        $codes = explode(',', $settings_two->languages);

        if ($request->state) {
            if (! in_array($request->lang, $codes)) {
                $codes[] = $request->lang;
            }
        } else {
            if (in_array($request->lang, $codes)) {
                unset($codes[array_search($request->lang, $codes)]);
            }
        }
        $settings_two->languages = implode(',', $codes);
        $settings_two->save();

        return response()->json(['code' => 200], 200);
    }

    public function imagesUpload(Request $request)
    {
        $images = $request->input('images');

        $paths = [];

        foreach ($images ?? [] as $image) {
            $base64Image = $image;
            $nameOfImage = Str::random(12) . '.png';

            // save file on local storage or aws s3
            Storage::disk('public')->put($nameOfImage, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image)));
            $path = '/uploads/' . $nameOfImage;

            $uploadedFile = new File(substr($path, 1));

            if (SettingTwo::getCache()->ai_image_storage == 's3') {
                try {
                    $aws_path = Storage::disk('s3')->put('', $uploadedFile);
                    unlink(substr($path, 1));
                    $path = Storage::disk('s3')->url($aws_path);
                } catch (Exception $e) {
                    return response()->json(['status' => 'error', 'message' => 'AWS Error - ' . $e->getMessage()]);
                }
            }

            if (SettingTwo::getCache()->ai_image_storage == 'r2') {
                try {
                    $aws_path = Storage::disk('r2')->put('', $uploadedFile);
                    unlink(substr($path, 1));
                    $path = Storage::disk('r2')->url($aws_path);
                } catch (Exception $e) {
                    return response()->json(['status' => 'error', 'message' => 'AWS Error - ' . $e->getMessage()]);
                }
            }

            $paths[] = $path;
        }

        return response()->json(['path' => $paths]);
    }

    public function filesUpload(Request $request): JsonResponse
    {
        $contentManagerActive = MarketplaceHelper::isRegistered('content-manager')
            && setting('content_manager_enabled', '1');

        $images = [];
        $others = [];
        $names = [];
        $allowedChatUploadExtensions = [
            'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'json', 'xml', 'html', 'htm', 'md', 'ppt', 'pptx',
            'mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm', 'ogg', 'flac', 'aac', 'mov', 'mkv', 'avi', 'm4v',
        ];
        $imageExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'];

        foreach ((array) $request->input('files', []) as $file) {
            if (is_object($file)) {
                $file = (array) $file;
            }
            if (! is_array($file)) {
                continue;
            }

            $name = trim((string) ($file['name'] ?? ''));
            $data = $file['data'] ?? null;
            if (! is_string($data) || trim($data) === '') {
                continue;
            }
            $data = trim($data);

            // Content Manager / previously uploaded assets may be inserted into
            // Chat Pro as an existing local /uploads/media/... path instead of a
            // base64 data URI. Reuse only files owned by the authenticated user.
            if (! str_starts_with($data, 'data:')) {
                $pathOnly = (string) (parse_url($data, PHP_URL_PATH) ?: $data);
                $pathOnly = '/' . ltrim($pathOnly, '/');
                $userMarker = '/u-' . auth()->id() . '/';

                if (str_starts_with($pathOnly, '/uploads/media/') && str_contains($pathOnly, $userMarker)) {
                    $candidate = public_path(ltrim($pathOnly, '/'));
                    $mediaRoot = realpath(public_path('uploads/media'));
                    $realCandidate = realpath($candidate);

                    if ($mediaRoot && $realCandidate && str_starts_with($realCandidate, $mediaRoot . DIRECTORY_SEPARATOR) && is_file($realCandidate)) {
                        $extension = strtolower((string) pathinfo($realCandidate, PATHINFO_EXTENSION));
                        if (in_array($extension, $allowedChatUploadExtensions, true) && validateUploadedFile($realCandidate, $extension)) {
                            $resolvedName = $name !== '' ? $name : basename($realCandidate);
                            $names[] = $resolvedName;
                            if (in_array($extension, $imageExtensions, true)) {
                                $images[] = $pathOnly;
                            } else {
                                $others[] = $pathOnly;
                            }
                            continue;
                        }
                    }
                }

                Log::warning('Chat Pro rejected an existing file reference', [
                    'user_id' => auth()->id(),
                    'path' => mb_substr($pathOnly, 0, 500),
                ]);
                continue;
            }

            if (! preg_match('/^data:([^;]*);base64,(.+)$/s', $data, $matches)) {
                continue;
            }

            $mimeType = strtolower(trim((string) ($matches[1] ?? '')));
            $rawData = base64_decode($matches[2], true);
            if ($rawData === false) {
                continue;
            }

            $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            if ($extension === '' && $mimeType !== '') {
                $extension = strtolower((string) mimeToExtension($mimeType));
            }
            if ($extension === '' || ! in_array($extension, $allowedChatUploadExtensions, true)) {
                continue;
            }

            // Base64 inflates request size. Keep the decoded asset bounded.
            if (strlen($rawData) > 100 * 1024 * 1024) {
                continue;
            }

            if ($name === '') {
                $name = 'upload-' . Str::random(8) . '.' . $extension;
            } elseif (! pathinfo($name, PATHINFO_EXTENSION)) {
                $name .= '.' . $extension;
            }

            $fileType = str_starts_with($mimeType, 'image/') || in_array($extension, $imageExtensions, true)
                ? 'images'
                : 'other';

            $relativePath = "uploads/media/{$fileType}/u-" . auth()->id();
            $absoluteDir = public_path($relativePath);

            if (! is_dir($absoluteDir) && ! mkdir($absoluteDir, 0775, true) && ! is_dir($absoluteDir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $absoluteDir));
            }

            if ($fileType === 'other' && $contentManagerActive) {
                $existingPath = $absoluteDir . '/' . basename($name);
                if (file_exists($existingPath) && validateUploadedFile($existingPath, $extension)) {
                    $publicPath = '/' . $relativePath . '/' . basename($name);
                    $uploadedFile = new File($existingPath);
                    $this->checkOtherStorage($uploadedFile, $publicPath);
                    $others[] = $publicPath;
                    $names[] = basename($name);
                    continue;
                }
            }

            $safeOriginalName = basename(str_replace(['\\', '/'], '-', $name));
            $uniqueName = ($fileType === 'other' && $contentManagerActive)
                ? $safeOriginalName
                : Str::random(10) . '-' . time() . '-' . $safeOriginalName;
            $absolutePath = $absoluteDir . '/' . $uniqueName;

            try {
                if (file_put_contents($absolutePath, $rawData) === false) {
                    Log::error("Failed to save file: {$absolutePath}");
                    continue;
                }

                if (! validateUploadedFile($absolutePath, $extension)) {
                    @unlink($absolutePath);
                    continue;
                }

                $publicPath = '/' . $relativePath . '/' . $uniqueName;
                $uploadedFile = new File($absolutePath);
                $this->checkOtherStorage($uploadedFile, $publicPath);
                $names[] = $safeOriginalName;

                if ($fileType === 'images') {
                    $images[] = $publicPath;
                } else {
                    $others[] = $publicPath;
                }
            } catch (Throwable $e) {
                Log::error("Upload failed for {$uniqueName}: " . $e->getMessage(), [
                    'user_id' => auth()->id(),
                ]);
            }
        }

        $allPaths = array_values(array_merge($others, $images));
        if ($allPaths === []) {
            return response()->json([
                'error' => true,
                'message' => __('The selected file is not supported or could not be uploaded.'),
            ], 422);
        }

        return response()->json([
            'type'   => $others !== [] ? 'other' : 'image',
            'path'   => $allPaths,
            'paths'  => $allPaths,
            'name'   => implode(',', $names),
            'names'  => $names,
            'others' => array_values($others),
            'images' => array_values($images),
        ]);
    }

    private function checkOtherStorage(File $uploadedFile, string &$path): void
    {
        try {
            $disk = SettingTwo::getCache()->ai_image_storage;

            if (! in_array($disk, ['s3', 'r2'], true)) {
                return;
            }

            $awsPath = Storage::disk($disk)->putFileAs('', $uploadedFile, $uploadedFile->getFilename());

            if ($awsPath === false) {
                Log::error("Cloud upload failed on disk [{$disk}] for {$uploadedFile->getPathname()}");

                return;
            }

            @unlink($uploadedFile->getPathname());
            $path = Storage::disk($disk)->url($awsPath);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function imageUpload(Request $request)
    {
        $image = $request->file('image');
        $title = $request->input('title');

        $imageContent = file_get_contents($image->getRealPath());
        $base64Image = base64_encode($imageContent);
        $nameOfImage = Str::random(12) . '.png';

        // save file on local storage or aws s3
        Storage::disk('public')->put($nameOfImage, base64_decode($base64Image));
        $path = '/uploads/' . $nameOfImage;
        $uploadedFile = new File(substr($path, 1));

        if (SettingTwo::getCache()->ai_image_storage == 's3') {
            try {
                $aws_path = Storage::disk('s3')->put('', $uploadedFile);
                unlink(substr($path, 1));
                $path = Storage::disk('s3')->url($aws_path);
            } catch (Exception $e) {
                return response()->json(['status' => 'error', 'message' => 'AWS Error - ' . $e->getMessage()]);
            }
        }

        if (SettingTwo::getCache()->ai_image_storage == 'r2') {
            try {
                $aws_path = Storage::disk('r2')->put('', $uploadedFile);
                unlink(substr($path, 1));
                $path = Storage::disk('r2')->url($aws_path);
            } catch (Exception $e) {
                return response()->json(['status' => 'error', 'message' => 'AWS Error - ' . $e->getMessage()]);
            }
        }

        return response()->json(['path' => "$path"]);
    }

    public function rssFetch(Request $request)
    {
        $data = parseRSS($request->url);

        if (! $data) {
            return response()->json(__('RSS Not Fetched! Please check your URL and validete the RSS!'), 419);

        }

        $html = '';

        foreach ($data as $post) {
            $html .= sprintf(
                '<option value="%1$s" data-image="%2$s">%1$s</option>',
                e($post['title']),
                e($post['image']),
            );
        }

        return response()->json($html, 200);
    }
}
