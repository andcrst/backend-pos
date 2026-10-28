<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ApiException::invalidCredentials([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ApiException::accountInactive();
        }

        $token = $user->createToken($request->validated('device_name') ?? 'api')->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('roles', 'permissions')),
        ], 'Login berhasil.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logout berhasil.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('roles', 'permissions');

        return ApiResponse::success(new UserResource($user));
    }

    /**
     * Ganti password dan cabut token perangkat lain.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            throw new ApiException(
                'Password saat ini tidak sesuai.',
                'INVALID_CURRENT_PASSWORD',
                422,
                ['current_password' => ['Password saat ini tidak sesuai.']],
            );
        }

        $user->forceFill(['password' => $request->validated('password')])->save();

        $currentTokenId = $user->currentAccessToken()?->getKey();

        $user->tokens()
            ->when($currentTokenId !== null, fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();

        return ApiResponse::success(null, 'Password berhasil diubah.');
    }
}
