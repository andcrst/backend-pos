<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as PermissionUnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport(ApiException::class);

        $exceptions->render(function (ApiException $e) {
            return ApiResponse::error($e->getMessage(), $e->errorCode, $e->status, $e->errors);
        });

        $exceptions->render(function (ValidationException $e) {
            return ApiResponse::error(
                'Data yang dikirim tidak valid.',
                'VALIDATION_ERROR',
                422,
                $e->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $e) {
            return ApiResponse::error('Autentikasi diperlukan.', 'UNAUTHENTICATED', 401);
        });

        $exceptions->render(function (AuthorizationException $e) {
            return ApiResponse::error('Anda tidak memiliki akses.', 'FORBIDDEN', 403);
        });

        $exceptions->render(function (PermissionUnauthorizedException $e) {
            return ApiResponse::error('Anda tidak memiliki akses.', 'FORBIDDEN', 403);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e) {
            return ApiResponse::error('Data tidak ditemukan.', 'NOT_FOUND', 404);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return ApiResponse::error(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Permintaan gagal diproses.',
                    $status === 429 ? 'TOO_MANY_REQUESTS' : 'HTTP_ERROR',
                    $status,
                );
            }

            return ApiResponse::error(
                config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server.',
                'INTERNAL_ERROR',
                500,
            );
        });
    })->create();
