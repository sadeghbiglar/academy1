<?php

use App\Enums\Section;
use App\Models\Student;
use App\Models\Teacher;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::academy')]
class extends Component {
    public string $title = 'داشبورد';

    public function render()
    {
        $user = auth()->user();

        // شمارش فقط برای نقش‌هایی که واقعاً به آن نیاز دارند. شمردن چیزی که
        // کاربر اجازه دیدنش را ندارد نشت اطلاعات است، حتی اگر فقط عدد باشد.
        $counts = [];

        if ($user->canSee(Section::Students)) {
            $counts['دانش‌آموزان'] = Student::count();
        }

        if ($user->canSee(Section::Teachers)) {
            $counts['اساتید'] = Teacher::count();
        }

        // بخش‌هایی که این نقش می‌بیند ولی هنوز ساخته نشده‌اند
        $pending = array_values(array_filter(
            Section::menuOrder(),
            fn (Section $section) => $user->canSee($section) && $section->url() === null,
        ));

        return view('pages.dashboard.index', compact('counts', 'pending'));
    }
}
?>