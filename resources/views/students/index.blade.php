<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>دانش‌آموزان</title>
</head>
<body>

    <h1>لیست دانش‌آموزان</h1>

    <ul>
        @foreach ($students as $student)
            <li>
                {{ $student['name'] }}
                -
                {{ $student['phone'] }}
            </li>
        @endforeach
    </ul>

</body>
</html>