<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = 'ok';

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $database = 'error';
        }

        $payload = [
            'app' => config('app.name'),
            'environment' => config('app.env'),
            'timezone' => config('app.timezone'),
            'database' => $database,
            'server_time' => now()->toIso8601String(),
        ];

        if ($database !== 'ok') {
            return ApiResponse::error(
                'Database tidak dapat diakses.',
                'DATABASE_UNAVAILABLE',
                503,
                [],
                $payload,
            );
        }

        return ApiResponse::success($payload, 'Aplikasi sehat.');
    }
}
