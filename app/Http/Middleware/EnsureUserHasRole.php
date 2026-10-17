<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * گیت نقش برای routeها: `->middleware('role:admin,finance')`.
 *
 * رد کردن کاربر مهم است: منو را پنهان کردن امنیت نیست. هر route حساس باید
 * این میدل‌ور را داشته باشد، حتی اگر UI آن را نشان نمی‌دهد.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->is_active === false) {
            // حساب غیرفعال: نشست را ببند و به ورود برگردان
            auth()->logout();

            return redirect()->route('login')
                ->withErrors(['email' => 'حساب کاربری شما غیرفعال شده است.']);
        }

        $allowed = array_map(fn ($r) => Role::tryFrom($r), $roles);
        $allowed = array_filter($allowed);

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'شما به این بخش دسترسی ندارید.');
        }

        return $next($request);
    }
}
