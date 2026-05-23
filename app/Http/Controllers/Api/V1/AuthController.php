<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Authenticate user and return a token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            Auth::logout();
            return response()->json([
                'message' => 'Akun Anda sedang ditangguhkan atau tidak aktif.',
            ], 403);
        }

        // Update last login
        $user->update([
            'last_login_at' => now(),
        ]);

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Load active organization memberships
        $organizations = $user->organizations()
            ->where('organizations.status', 'active')
            ->where('organization_members.status', 'active')
            ->get()
            ->map(fn ($org) => [
                'id' => $org->id,
                'uuid' => $org->uuid,
                'name' => $org->name,
                'slug' => $org->slug,
                'role' => $org->pivot->role_name,
            ]);

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
            ],
            'organizations' => $organizations,
        ]);
    }

    /**
     * Log the user out (Revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
            ],
        ]);
    }

    /**
     * Get organizations list of authenticated user.
     */
    public function organizations(Request $request): JsonResponse
    {
        $user = $request->user();

        $organizations = $user->organizations()
            ->where('organizations.status', 'active')
            ->where('organization_members.status', 'active')
            ->get()
            ->map(fn ($org) => [
                'id' => $org->id,
                'uuid' => $org->uuid,
                'name' => $org->name,
                'slug' => $org->slug,
                'role' => $org->pivot->role_name,
            ]);

        return response()->json([
            'organizations' => $organizations,
        ]);
    }
}
