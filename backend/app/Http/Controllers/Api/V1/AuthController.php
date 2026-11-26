<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\UserOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserOnboardingService $onboarding,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $this->onboarding->initializeUser(
            $user,
            $data['opening_balance'] ?? '0.00',
            $data['quit_date'] ?? null
        );

        $token = $user->createToken('regain-pwa')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'created_at']),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi tidak valid.'],
            ]);
        }

        // Ensure existing users have baseline settings/categories
        $this->onboarding->initializeUser($user);

        $token = $user->createToken('regain-pwa')->plainTextToken;

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'created_at']),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('appSetting');

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'created_at']),
            'settings' => $user->appSetting,
        ]);
    }
}
