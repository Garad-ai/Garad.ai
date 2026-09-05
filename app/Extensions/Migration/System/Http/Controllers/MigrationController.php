<?php

namespace App\Extensions\Migration\System\Http\Controllers;

use App\Extensions\Migration\System\Enums\MigrationDriverEnum;
use App\Extensions\Migration\System\Services\MigrationService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MigrationController extends Controller
{
    private mixed $migrationService;

    public function __construct()
    {
        $this->migrationService = app(MigrationService::class);
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 600);
        ini_set('upload_max_filesize', '200M');
        ini_set('post_max_size', '200M');
    }

    public function index()
    {
        return view('migration::welcome');
    }

    public function start()
    {

        $providers = $this->migrationService->getAvailableProviders();
        $capabilities = collect($providers)->mapWithKeys(function ($provider) {
            return [
                $provider->enum()->value => array_values($provider->supportedCapabilities()),
            ];
        });

        return view('migration::start', compact('providers', 'capabilities'));
    }

    public function migrate(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return redirect()->route('dashboard.index')->with([
                'type'    => 'error',
                'message' => __('Migration is not allowed in the demo version.'),
            ]);
        }

        $supportedProviders = $this->migrationService->getAvailableProviders();
        $validated = $request->validate([
            'provider'  => 'required|in:' . implode(',', array_map(static fn ($e) => $e->enum()->value, $supportedProviders)),
            'sql_file'  => [
                'nullable',
                'file',
                function ($attribute, $value, $fail) {
                    if ($value->guessExtension() !== 'sql') {
                        $fail("The {$attribute} must be a file of type: sql.");
                    }
                },
            ],
            'env_file'   => [
                'nullable',
                'file',
                function ($attribute, $value, $fail) {
                    if ($value && $value->guessExtension() !== 'env') {
                        $fail("The {$attribute} must be a file of type: env.");
                    }
                },
            ],
        ]);

        try {
            $options = [];

            $this->deleteOldMigrationFiles();

            if ($request->hasFile('sql_file')) {
                $sqlPath = $request->file('sql_file')?->store('migrations/tmp');
                $options['sql_file'] = storage_path("app/{$sqlPath}");
            }

            if ($request->hasFile('env_file')) {
                $envPath = $request->file('env_file')?->store('migrations/tmp');
                $options['env_file'] = storage_path("app/{$envPath}");
            }

            $result = $this->migrationService->migrate(MigrationDriverEnum::tryFrom($validated['provider']), $options);

            \auth()->logout();

            return redirect()->route('login')->with([
                'type'    => 'success',
                'message' => __('Migration done successfully! Please login with your new credentials.'),
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with([
                'type'    => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function deleteOldMigrationFiles(): void
    {
        $tmpDir = storage_path('app/migrations/tmp');

        if (! is_dir($tmpDir)) {
            return;
        }

        foreach (glob("{$tmpDir}/*.{sql,env,txt}", GLOB_BRACE) as $file) {
            @unlink($file);
        }
    }
}
