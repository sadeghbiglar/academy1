<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Student;
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
    public function __construct(private readonly BalanceReport $report) {}

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
