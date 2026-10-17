@php
    // layouts/academy یک قالب است و خودش namespace ندارد؛ بدون این use
    // نمی‌تواند به enum ارجاع بدهد.
    use App\Enums\Section;
@endphp

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'آموزشگاه' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="min-h-screen bg-base-200">

<div class="drawer lg:drawer-open">

    {{-- ناوبری از روی Section ساخته می‌شود، نه با لیست دستیِ نام و مسیر.
         لینکی که هنوز ساخته نشده رندر نمی‌شود، پس منو هیچ‌وقت لینک شکسته
         ندارد؛ ولی بخشِ ساخته‌نشده در «نقشه راه» صفحه داشبورد دیده می‌شود تا
         کاربر بداند چه چیزی در راه است. این تجربه کاربری است، نه امنیت. --}}
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

                <ul class="menu p-2 gap-0.5 w-full grow">
                    @foreach (Section::menuOrder() as $section)
                        @continue (! auth()->user()->canSee($section))
                        @php $url = $section->url(); @endphp

                        @if ($url)
                            <li>
                                <a href="{{ $url }}"
                                   @class(['menu-active' => request()->routeIs($section->routeName())])>
                                    <span>{{ $section->icon() }}</span>
                                    {{ $section->label() }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>

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
