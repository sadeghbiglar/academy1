<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Student;
use App\Services\AcademicReport;
use App\Services\BalanceReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * گزارش‌ها.
 *
 * بازهٔ تاریخ همیشه شمسی از کاربر می‌آید و میلادی تبدیل و در گزارش اعمال
 * می‌شود. کاربر تاریخ میلادی نمی‌شناسد؛ تبدیل در فرم گم شود، گزارش «بازهٔ
 * اشتباه» می‌دهد و هیچ خطایی هم نشان نمی‌دهد.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly BalanceReport $report,
        private readonly AcademicReport $academic,
    ) {}

    public function balances(Request $request): View
    {
        $data = $request->validate([
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
            'only_debtors' => ['nullable'],
        ]);

        $from = $this->toMiladi($data['from'] ?? null);
        $to = $this->toMiladi($data['to'] ?? null);

        $students = Student::query()->orderBy('id')->get();

        $rows = $this->report->build($students, $from, $to);

        if ($request->boolean('only_debtors')) {
            $rows = $rows->filter(fn (array $row) => $row['outstanding'] > 0)->values();
        }

        $byMethod = $this->report->collectedByMethod($from, $to);

        return view('reports.balances', [
            'rows' => $rows,
            'totals' => $this->report->totals($rows),
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'onlyDebtors' => $request->boolean('only_debtors'),
            'byMethod' => $this->labelMethods($byMethod),
            'totalOutstanding' => $this->report->totalOutstanding($from, $to),
        ]);
    }

    /** گزارش‌های آموزشی: حضور، نمرات، کم‌حضورها. */
    public function academic(Request $request): View
    {
        $data = $request->validate([
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
            'previous_from' => ['nullable', 'string'],
            'previous_to' => ['nullable', 'string'],
        ]);

        $from = $this->toMiladi($data['from'] ?? null);
        $to = $this->toMiladi($data['to'] ?? null);

        $previousFrom = $this->toMiladi($data['previous_from'] ?? null);
        $previousTo = $this->toMiladi($data['previous_to'] ?? null);

        $students = Student::query()->orderBy('id')->get();

        $academic = $this->academic;

        $lowAttendance = $academic->lowAttendance($students, $from, $to);

        // مقایسه با بازهٔ قبل فقط وقتی بازهٔ قبل داده شده باشد معنی دارد؛
        // بدون آن «رشد» نسبت به هیچ چیز محاسبه می‌شد
        $progress = null;

        if ($previousFrom || $previousTo) {
            $progress = $students
                ->mapWithKeys(fn (Student $student) => [
                    $student->id => $academic->compare(
                        $student,
                        ['from' => $from, 'to' => $to],
                        ['from' => $previousFrom, 'to' => $previousTo],
                    ),
                ])
                ->filter(fn (array $delta) => $delta['attendance_delta'] !== null || $delta['score_delta'] !== null)
                ->map(fn (array $delta) => $delta + [
                    'attendance_trend' => $academic->trendLabel($delta['attendance_delta']),
                    'score_trend' => $academic->trendLabel($delta['score_delta']),
                ]);
        }

        return view('reports.academic', [
            'rows' => $students->map(fn (Student $student) => [
                'student' => $student,
                'attendance' => $academic->attendanceFor($student, $from, $to),
                'average' => $academic->rawAverage($student, $from, $to),
                'grades' => $academic->gradesFor($student),
            ]),
            'lowAttendance' => $lowAttendance,
            'sessions' => $academic->sessionsIn($from, $to),
            'progress' => $progress,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'previousFrom' => $data['previous_from'] ?? null,
            'previousTo' => $data['previous_to'] ?? null,
        ]);
    }

    /** تبدیل ورودی شمسی به میلادی، با خطای قابل فهم. */
    private function toMiladi(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return miladi_date($value);
        } catch (\InvalidArgumentException) {
            // تاریخ نامعتبر یعنی بازهٔ خالی، نه خطای مرگبار؛ ولی نباید بی‌صدا
            // به کل تاریخ‌ها تبدیل شود، پس مقدار خالی می‌ماند
            return null;
        }
    }

    /**
     * @param  array<string, int>  $byMethod
     * @return array<string, int>
     */
    private function labelMethods(array $byMethod): array
    {
        $out = [];

        foreach ($byMethod as $method => $total) {
            $label = PaymentMethod::tryFrom((string) $method)?->label() ?? 'نامشخص';

            $out[$label] = ($out[$label] ?? 0) + (int) $total;
        }

        return $out;
    }
}
