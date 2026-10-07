<?php

use App\Models\Teacher;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::academy')]
class extends Component
{
    use WithPagination;

    public string $title = 'اساتید آموزشگاه';

    public string $search = '';

    public array $headers = [
        ['key' => 'row_number', 'label' => '#'],
        ['key' => 'full_name', 'label' => 'نام و نام خانوادگی'],
        ['key' => 'national_code', 'label' => 'کد ملی'],
        ['key' => 'gender', 'label' => 'جنسیت'],
        ['key' => 'mobile', 'label' => 'موبایل'],
        ['key' => 'subject', 'label' => 'رشته'],
        ['key' => 'hire_date', 'label' => 'تاریخ استخدام'],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function teachers()
    {
        $teachers = Teacher::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query
                        ->where('first_name', 'like', '%'.$this->search.'%')
                        ->orWhere('last_name', 'like', '%'.$this->search.'%')
                        ->orWhere('mobile', 'like', '%'.$this->search.'%')
                        ->orWhere('national_code', 'like', '%'.$this->search.'%');
                });
            })
            // Without a deterministic order MySQL may return rows in any
            // order, which makes paginate() repeat or skip rows across pages.
            ->orderByDesc('id')
            ->paginate(10);

        $teachers->getCollection()->transform(
            function ($teacher, $index) use ($teachers) {
                $teacher->row_number = $teachers->firstItem() + $index;

                $teacher->full_name = "{$teacher->first_name} {$teacher->last_name}";

                return $teacher;
            }
        );

        return $teachers;
    }
};

?>

<div class="p-6">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">

        <div>
            <h1 class="text-2xl font-bold">
                {{ $title }}
            </h1>

            <p class="text-sm opacity-60 mt-1">
                مدیریت اطلاعات اساتید آموزشگاه
            </p>
        </div>

    </div>

    <x-alert title="مدیریت اساتید" description="در این بخش می‌توانید اطلاعات اساتید آموزشگاه را مشاهده کنید."
        icon="o-information-circle" class="mb-6" />

    <div class="mb-6">

        <x-input label="جستجوی استاد" placeholder="نام، نام خانوادگی، موبایل یا کد ملی..." icon="o-magnifying-glass"
            wire:model.live="search" />

    </div>

    <div class="bg-base-100 rounded-box shadow">

        @if ($this->teachers->isEmpty())
        <div class="bg-base-100 rounded-box shadow p-10 text-center">
            <div class="flex justify-center mb-4">
                <x-icon
                    name="o-magnifying-glass"
                    class="w-12 h-12 opacity-30" />
            </div>

            <h3 class="text-lg font-bold">
                استادی پیدا نشد
            </h3>

            <p class="text-sm opacity-60 mt-2">
                @if ($search)
                استادی با عبارت «{{ $search }}» پیدا نشد.
                @else
                هنوز هیچ استادی ثبت نشده است.
                @endif
            </p>

            @if ($search)
            <x-button
                label="پاک کردن جستجو"
                icon="o-x-mark"
                class="btn-sm mt-4"
                wire:click="$set('search', '')" />
            @endif
        </div>
        @else
        <div class="bg-base-100 rounded-box shadow overflow-hidden">
            <div class="overflow-x-auto">
                <x-table
                    :headers="$headers"
                    :rows="$this->teachers"
                    striped
                    hover>

                    @scope('cell_gender', $teacher)
                    @if ($teacher->gender === 'male')
                    آقا
                    @elseif ($teacher->gender === 'female')
                    خانم
                    @else
                    —
                    @endif
                    @endscope

                    @scope('cell_hire_date', $teacher)
                    {{ jalali_date($teacher->hire_date) ?? '—' }}
                    @endscope

                </x-table>
            </div>
        </div>

        <div class="mt-4">
            {{ $this->teachers->links() }}
        </div>
        @endif

    </div>

</div>