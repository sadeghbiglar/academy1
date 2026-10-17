<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        {{ $title ?? 'آموزشگاه' }}
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="min-h-screen bg-base-200">

<div class="drawer lg:drawer-open">

    {{-- ناوبری: هر لینک فقط وقتی رندر می‌شود که نقش کاربر اجازه داشته باشد.
         این تجربه کاربری است، نه امنیت — امنیت در لایه route اعمال می‌شود. --}}
    <input id="app-drawer" type="checkbox" class="drawer-toggle">

    <div class="drawer-content">
        <div class="navbar bg-base-100 shadow-sm lg:hidden">
            <label for="app-drawer" class="btn btn-ghost">☰</label>
            <span class="font-bold">🎓 آموزشگاه</span>
        </div>

        <div class="p-6">
            <main>
                {{ $slot }}
                <x-toast />
            </main>
        </div>
    </div>

    <div class="drawer-side z-40">
        <label for="app-drawer" class="drawer-overlay"></label>

        <aside class="w-64 min-h-full bg-base-100 border-l border-base-200 flex flex-col">
            <div class="p-4 border-b border-base-200">
                <div class="font-bold text-lg">🎓 آموزشگاه</div>
                <div class="text-xs opacity-60">سامانه مدیریت آموزشگاه خصوصی</div>
            </div>

            @auth
                <div class="px-4 py-3 text-sm border-b border-base-200">
                    <div class="font-bold">{{ auth()->user()->display_name }}</div>
                    <span class="badge badge-sm badge-primary mt-1">{{ auth()->user()->role_label }}</span>
                </div>
            @endauth

            <ul class="menu p-2 gap-0.5 w-full grow">
                @php
                    $sections = [
                        'dashboard' => ['داشبورد', 'dashboard'],
                        'students' => ['دانش‌آموزان', 'students.index'],
                        'teachers' => ['اساتید', 'teachers.index'],
                        'finance' => ['مالی', 'finance.index'],
                        'reports' => ['گزارش‌ها', 'reports.index'],
                        'settings' => ['تنظیمات', 'settings.index'],
                        'users' => ['کاربران', 'users.index'],
                    ];
                @endphp

                @auth
                    @foreach ($sections as $key => [$label, $routeName])
                        @if (auth()->user()->canSee($key) && Route::has($routeName))
                            <li>
                                <a href="{{ route($routeName) }}"
                                   @class(['menu-active' => request()->routeIs($routeName)])>
                                    {{ $label }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                @endauth
            </ul>

            @auth
                <div class="p-3 border-t border-base-200">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm w-full justify-start">
                            خروج از حساب
                        </button>
                    </form>
                </div>
            @endauth
        </aside>
    </div>
</div>

@livewireScripts

</body>

</html>