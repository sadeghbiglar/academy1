<div>
    <h1 class="text-2xl font-bold mb-4">داشبورد</h1>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <p class="text-sm opacity-70">خوش آمدید</p>
                <p class="text-xl font-bold">{{ auth()->user()->display_name }}</p>
                <span class="badge badge-primary mt-2">{{ auth()->user()->role_label }}</span>
            </div>
        </div>

        @foreach ($counts as $label => $value)
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <p class="text-sm opacity-70">{{ $label }}</p>
                    <p class="text-3xl font-bold">{{ to_persian_digits($value) }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @if ($pending !== [])
        <div class="card bg-base-100 shadow mt-4">
            <div class="card-body">
                <h2 class="card-title text-base">در دست ساخت</h2>
                <p class="text-sm opacity-70">
                    این بخش‌ها در نقشه راه هستند و به‌زودی فعال می‌شوند.
                </p>

                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach ($pending as $section)
                        <span class="badge badge-ghost">
                            {{ $section->icon() }} {{ $section->label() }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
