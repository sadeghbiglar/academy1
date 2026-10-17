<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * مالی: فاکتورها، دریافت پرداخت، رسید و فاکتور چاپی.
 *
 * چاپ یک view جدا از layout است، نه یک صفحهٔ معمولی با CSS چاپ: پنل مدیریت
 * منو و سایه دارد و آن‌ها روی کاغذ می‌افتند. رسید خانواده باید تمیز باشد.
 */
class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $status = InvoiceStatus::tryFrom((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));

        $invoices = Invoice::query()
            ->with(['student', 'course'])
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->when($search !== '', function ($query) use ($search) {
                // جستجو با نام دانش‌آموز، نه فقط شمارهٔ فاکتور؛ پذیرش اسم را
                // می‌شناسد نه شماره را
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('student', fn ($s) => $s
                            ->where('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhere('national_code', 'like', '%'.$search.'%'));
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('finance.index', [
            'invoices' => $invoices,
            'status' => $status,
            'search' => $search,
            'statuses' => InvoiceStatus::cases(),
            'totalReceivable' => (int) Invoice::query()
                ->where('status', InvoiceStatus::Overdue->value)->sum('payable'),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        return view('finance.show', [
            'invoice' => $invoice->load(['student', 'course', 'payments.user', 'installments']),
        ]);
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string'],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $invoice->status->acceptsPayment()) {
            return back()->with('error', 'این فاکتور در وضعیت فعلی پرداخت نمی‌پذیرد.');
        }

        $method = PaymentMethod::tryFrom($data['method']);

        if ($method === null) {
            return back()->with('error', 'روش پرداخت نامعتبر است.');
        }

        Payment::pay(
            $invoice,
            (int) $data['amount'],
            $method,
            $data['paid_at'],
            $data['reference'] ?? null,
            auth()->user(),
            $data['notes'] ?? null,
        );

        return back()->with('success', 'پرداخت ثبت شد.');
    }

    /** فاکتور چاپی. بدون layout پنل. */
    public function printInvoice(Invoice $invoice): View
    {
        return view('finance.print.invoice', [
            'invoice' => $invoice->load(['student', 'course', 'payments.user', 'installments']),
            'issuedBy' => auth()->user(),
        ]);
    }

    /** رسید پرداخت چاپی. */
    public function printReceipt(Payment $payment): View
    {
        return view('finance.print.receipt', [
            'payment' => $payment->load(['invoice.student', 'invoice.course', 'user']),
        ]);
    }
}
