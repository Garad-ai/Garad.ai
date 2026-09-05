<?php

use App\Extensions\AiViralClips\System\Http\Controllers\AiViralKlapController;
use App\Extensions\AiViralClips\System\Http\Controllers\KlapSettingController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['web', 'auth'],
    'prefix'     => 'ai-viral-clips',
    'as'         => 'ai-viral-clips.',
], function () {
    Route::post('generate-shorts', [AiViralKlapController::class, 'generateShorts'])->name('generate-shorts');
    Route::get('check-clip-status/{task_id}', [AiViralKlapController::class, 'checkTaskStatus'])->name('check-clip-status');
    Route::get('preview-lists/{folder_id}', [AiViralKlapController::class, 'previewLists'])->name('preview-lists');

    Route::post('export-clips', [AiViralKlapController::class, 'exportClips'])->name('export-clips');
    Route::get('export-video-status', [AiViralKlapController::class, 'checkExportStatus'])->name('export-video-status');

    Route::post('store-final-result-klap', [AiViralKlapController::class, 'storeFinalVideoKlap'])->name('store-final-result-klap');
});

Route::controller(KlapSettingController::class)
    ->prefix('dashboard/admin/settings')
    ->middleware(['web', 'auth', 'admin', 'is_not_demo'])
    ->name('dashboard.admin.settings.')->group(function () {
        Route::get('klap', 'index')->name('klap');
        Route::post('klap', 'update')->name('klap.update');
    });
