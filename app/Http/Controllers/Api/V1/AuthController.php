<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @tags Mobile Auth
 */
class AuthController extends Controller
{
    /**
     * Login staff (owner / cashier / kitchen).
     *
     * Mengembalikan data user beserta daftar organisasi yang diikuti
     * (termasuk `role` per organisasi dari tabel `organization_members`)
     * dan Bearer token untuk dipakai di endpoint berikutnya.
     *
     * Masukkan token ke tombol Authorize sebagai Bearer Token.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        // Update last_login_at
        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'user'  => new UserResource($user->load('organizations')),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout — mencabut (revoke) token akses yang sedang dipakai.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /**
     * Profil user yang sedang login beserta daftar organisasi & role-nya.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()->load('organizations')),
        ]);
    }

    /**
     * Update profil user yang sedang login (nama, telepon, avatar).
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'data'    => new UserResource($user->fresh()->load('organizations')),
            'message' => 'Profil berhasil diupdate.',
        ]);
    }

    /**
     * Upload avatar user yang sedang login (multipart/form-data).
     *
     * Field file: `avatar` (jpeg/jpg/png/webp, maks 2 MB). Avatar lama otomatis
     * dihapus. Mengembalikan user dengan `avatar` berisi URL siap pakai.
     */
    public function uploadAvatar(UploadAvatarRequest $request, MediaService $media): JsonResponse
    {
        $user = $request->user();

        $path = $media->replace($request->file('avatar'), 'users/avatars', $user->avatar);
        $user->update(['avatar' => $path]);

        return response()->json([
            'data'    => new UserResource($user->fresh()->load('organizations')),
            'message' => 'Avatar berhasil diunggah.',
        ]);
    }
}
