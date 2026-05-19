<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kennofizet\ReleaseSupport\Controllers\DrawingController;
use Kennofizet\ReleaseSupport\Controllers\ReleaseSupportController;

$prefix = config('packages-core.api_prefix', 'api/knf');
$supportPrefix = config('release-support.api_prefix', 'release-support');
$rateLimit = config('packages-core.rate_limit', 60);
$reportSubmitLimit = (int) config('release-support.report_submit_rate_limit', 10);

$baseMiddleware = ['api', "throttle:{$rateLimit},1", 'knf.core.token', 'knf.core.validator'];

Route::prefix($prefix . '/' . $supportPrefix)
    ->middleware($baseMiddleware)
    ->group(function () use ($reportSubmitLimit) {
        Route::get('bootstrap', [ReleaseSupportController::class, 'bootstrap']);
        Route::post('reports', [ReleaseSupportController::class, 'submitReport'])
            ->middleware("throttle:{$reportSubmitLimit},1");
        Route::get('reports/my', [ReleaseSupportController::class, 'myReports']);
        Route::get('reports/{reportId}', [ReleaseSupportController::class, 'reportDetail']);
        Route::get('drawings/{reportId}/{filename}', [DrawingController::class, 'show'])
            ->where('filename', '[A-Za-z0-9._-]+');

        Route::prefix('dev')->group(function () {
            Route::get('reports', [ReleaseSupportController::class, 'devReports']);
            Route::post('reports/{reportId}/status', [ReleaseSupportController::class, 'devUpdateStatus']);
            Route::post('reports/{reportId}/comments', [ReleaseSupportController::class, 'devAddComment']);
            Route::get('version-updates', [ReleaseSupportController::class, 'devVersionUpdates']);
            Route::post('version-updates', [ReleaseSupportController::class, 'devCreateVersionUpdate']);
            Route::put('version-updates/{id}', [ReleaseSupportController::class, 'devUpdateVersionUpdate']);
            Route::get('metrics', [ReleaseSupportController::class, 'devMetrics']);
        });
    });
