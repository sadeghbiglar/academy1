<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::academy')]
class extends Component {
    public string $title = 'داشبورد';

    public function render()
    {
        return <<<'HTML'
        <div>
            <h1 class="text-2xl font-bold mb-4">داشبورد</h1>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <p class="text-sm opacity-70">خوش آمدید</p>
                        <p class="text-xl font-bold">{{ auth()->user()->display_name }}</p>
                        <span class="badge badge-primary mt-2">{{ auth()->user()->role_label }}</span>
                    </div>
                </div>

                @if (auth()->user()->isTeacher() && auth()->user()->teacher)
                    <div class="card bg-base-100 shadow">
                        <div class="card-body">
                            <p class="text-sm opacity-70">استاد</p>
                            <p class="text-lg font-bold">
                                {{ auth()->user()->teacher->first_name }} {{ auth()->user()->teacher->last_name }}
                            </p>
                            <p class="text-sm opacity-70">{{ auth()->user()->teacher->subject }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        HTML;
    }
}
?>
