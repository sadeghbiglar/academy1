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

    <div class="p-6">

        <div class="mb-6">
            <h1 class="text-2xl font-bold">
                🎓 آموزشگاه خصوصی
            </h1>
            <p class="text-sm opacity-60">
                سامانه مدیریت آموزشگاه
            </p>
        </div>

        <main>
            {{ $slot }}
            <x-toast />
        </main>

    </div>

    @livewireScripts

</body>

</html>