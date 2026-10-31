<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** حداکثر تلاش ناموفق در هر پنجره‌ی یک‌دقیقه‌ای (برای هر ایمیل+IP) */
    private const MAX_ATTEMPTS = 5;

    /** مدت قفل پس از رسیدن به سقف تلاش (ثانیه) */
    private const DECAY_SECONDS = 60;

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $key = $this->throttleKey($request, $data['email']);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "تعداد تلاش‌های ورود بیش از حد است. {$seconds} ثانیه دیگر دوباره تلاش کنید.",
                'retry_after' => $seconds,
            ], 429)->header('Retry-After', $seconds);
        }

        $user = User::where('email', $data['email'])->where('is_active', true)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => ['ایمیل یا رمز عبور اشتباه است.'],
            ]);
        }

        RateLimiter::clear($key);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'branch' => $user->branch?->only(['id', 'name', 'code']),
            ],
            'token' => $user->createToken('auth_token')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'با موفقیت خارج شدید.']);
    }

    private function throttleKey(Request $request, string $email): string
    {
        return 'login:'.Str::lower(trim($email)).'|'.$request->ip();
    }
}
