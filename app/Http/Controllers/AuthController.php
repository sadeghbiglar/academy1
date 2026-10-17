<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();

        // محدودیت تلاش ورود: آموزشگاه رمز ضعیف دارد و بدون محدودیت، قابل حدس است
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'تلاش‌های ناموفق زیاد بود. لطفاً '.RateLimiter::availableIn($key).' ثانیه دیگر تلاش کنید.',
            ]);
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($key, 300);

            // پیام یکسان برای رمز اشتباه و ایمیل ناموجود: نباید فاش کند کدام
            // ایمیل در سامانه ثبت شده است.
            throw ValidationException::withMessages([
                'email' => 'ایمیل یا رمز عبور نادرست است.',
            ]);
        }

        // حساب غیرفعال نباید حتی با رمز درست وارد شود
        if ($request->user()->is_active === false) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'حساب کاربری شما غیرفعال شده است. با مدیر تماس بگیرید.',
            ]);
        }

        RateLimiter::clear($key);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
