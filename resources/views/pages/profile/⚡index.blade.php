<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::academy')]
class extends Component {
    public string $title = 'پروفایل من';

    public function render()
    {
        return <<<'HTML'
        <div>
            <h1 class="text-2xl font-bold mb-4">پروفایل من</h1>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-1">
                    <p><span class="opacity-70">نام:</span> {{ auth()->user()->display_name }}</p>
                    <p><span class="opacity-70">ایمیل:</span> {{ auth()->user()->email }}</p>
                    <p><span class="opacity-70">موبایل:</span> {{ to_persian_digits(auth()->user()->mobile) ?: '—' }}</p>
                    <p><span class="opacity-70">نقش:</span> {{ auth()->user()->role_label }}</p>
                    <p class="text-xs opacity-50 mt-2">
                        عضو از {{ jalali_date(auth()->user()->created_at, 'Y/m/d') }}
                    </p>
                </div>
            </div>
        </div>
        HTML;
    }
}
?>
