<?php

use App\Extensions\AiViralClips\System\Http\Controllers\AiViralVizardController;
use App\Extensions\AiViralClips\System\Http\Controllers\VizardSettingController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['web', 'auth'],
    'prefix'     => 'ai-viral-clips/vizard',
    'as'         => 'ai-viral-clips.vizard.',
], function () {
    Route::post('generate-shorts', [AiViralVizardController::class, 'generateShorts'])->name('generate-shorts');
    Route::get('retrieve-clips/{task_id}', [AiViralVizardController::class, 'retrieveClips'])->name('retrieve-clips');

    Route::post('store-final-result-vizard', [AiViralVizardController::class, 'storeFinalVideoVizard'])->name('store-final-result-vizard');
});

Route::controller(VizardSettingController::class)
    ->prefix('dashboard/admin/settings')
    ->middleware(['web', 'auth', 'admin', 'is_not_demo'])
    ->name('dashboard.admin.settings.')->group(function () {
        Route::get('vizard', 'index')->name('vizard');
        Route::post('vizard', 'update')->name('vizard.update');
    });
