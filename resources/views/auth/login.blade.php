<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سامانه آموزشگاه</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 flex items-center justify-center">
    <div class="card bg-base-100 shadow-xl w-full max-w-sm">
        <div class="card-body">
            <h1 class="card-title text-xl">🎓 ورود به سامانه</h1>
            <p class="text-sm opacity-70 mb-4">آموزشگاه خصوصی</p>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-3">
                @csrf

                <label class="form-control w-full">
                    <span class="label-text mb-1">ایمیل</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="input input-bordered w-full @error('email') border-red-500 @enderror">
                </label>

                @error('email')
                    <p class="text-red-600 text-sm">{{ $message }}</p>
                @enderror

                <label class="form-control w-full">
                    <span class="label-text mb-1">رمز عبور</span>
                    <input type="password" name="password" required
                           class="input input-bordered w-full">
                </label>

                <label class="label cursor-pointer justify-start gap-2">
                    <input type="checkbox" name="remember" value="1" class="checkbox checkbox-sm">
                    <span class="label-text">مرا به خاطر بسپار</span>
                </label>

                <button type="submit" class="btn btn-primary w-full">ورود</button>
            </form>
        </div>
    </div>
</body>
</html>
