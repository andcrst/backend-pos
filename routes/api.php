<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'type' => 'API',
        'version' => 'v1',
        'health' => url('/api/v1/health'),
    ]);
})->name('api.info');

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class)->name('api.v1.health');

    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/master.php';
});
