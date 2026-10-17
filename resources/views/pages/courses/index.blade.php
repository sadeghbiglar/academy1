<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">دوره‌ها</h1>

        <x-button
            label="دورهٔ جدید"
            icon="o-plus"
            wire:click="$set('showCreateModal', true)" />
    </div>

    <div class="flex justify-end mb-4">
        <div class="stats shadow bg-base-100">
            <div class="stat py-2">
                <div class="stat-title text-xs">کل دوره‌ها</div>
                <div class="stat-value text-lg">{{ to_persian_digits($this->courses->total()) }}</div>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 shadow mb-4">
        <div class="card-body py-4">
            <div class="grid gap-3 md:grid-cols-3">
                <x-input
                    label="جست‌وجو"
                    wire:model.live.debounce.300ms="search"
                    placeholder="عنوان دوره، درس یا نام استاد" />

                <x-select
                    label="وضعیت"
                    wire:model.live="statusFilter"
                    :options="$statusOptions"
                    option-value="id"
                    option-label="name"
                    placeholder="همهٔ وضعیت‌ها" />

                <div class="flex items-end">
                    <x-button
                        label="پاک کردن فیلترها"
                        icon="o-x-mark"
                        class="btn-sm btn-ghost"
                        wire:click="clearFilters" />
                </div>
            </div>
        </div>
    </div>

    @if ($this->courses->isEmpty())
        <div class="card bg-base-100 shadow">
            <div class="card-body items-center text-center py-12">
                <x-icon name="o-magnifying-glass" class="w-12 h-12 opacity-30" />

                <h3 class="text-lg font-bold">دوره‌ای پیدا نشد</h3>

                <p class="text-sm opacity-60 mt-2">
                    @if ($search || $statusFilter)
                        دوره‌ای با این فیلترها پیدا نشد.
                    @else
                        هنوز هیچ دوره‌ای ثبت شده است.
                    @endif
                </p>

                @if ($search || $statusFilter)
                    <x-button
                        label="پاک کردن فیلترها"
                        icon="o-x-mark"
                        class="btn-sm mt-4"
                        wire:click="clearFilters" />
                @endif
            </div>
        </div>
    @else
        <div class="bg-base-100 rounded-box shadow overflow-hidden">
            <div class="overflow-x-auto">
                <x-table :headers="$headers" :rows="$this->courses" striped hover>
                    @scope('cell_row_number', $course)
                        {{ to_persian_digits($course->row_number) }}
                    @endscope

                    @scope('cell_subject', $course)
                        {{ $course->subject?->name ?? '—' }}
                    @endscope

                    @scope('cell_teacher', $course)
                        {{ $course->teacher ? $course->teacher->first_name.' '.$course->teacher->last_name : 'تعیین نشده' }}
                    @endscope

                    @scope('cell_capacity', $course)
                        {{--
                            @scope محیط جدیدی است و دادهٔ والد را به ارث نمی‌برد،
                            پس $counts اینجا تعریف‌نشده بود و هر دوره بی‌صدا
                            «۰ / ظرفیت» نشان می‌داد. باید از خود کامپوننت خوانده شود.
                        --}}
                        @php $taken = $this->confirmedCounts()[$course->id] ?? 0; @endphp

                        @if ($course->capacity === 0)
                            <span class="text-xs opacity-60">نامحدود</span>
                        @else
                            <span @class(['text-error font-bold' => $taken >= $course->capacity])>
                                {{ to_persian_digits($taken) }} / {{ to_persian_digits($course->capacity) }}
                            </span>
                        @endif
                    @endscope

                    @scope('cell_base_fee', $course)
                        @if ($course->base_fee === 0)
                            <span class="badge badge-ghost badge-sm">رایگان</span>
                        @else
                            {{ to_persian_digits(number_format($course->base_fee / 10)) }} تومان
                        @endif
                    @endscope

                    @scope('cell_status', $course)
                        <span class="badge badge-sm {{ $course->status?->color() }}">
                            {{ $course->statusLabel() }}
                        </span>
                    @endscope

                    @scope('cell_starts_at', $course)
                        {{ $course->startsAtJalali() ?? '—' }}
                    @endscope

                    @scope('actions', $course)
                        <div class="flex items-center gap-1">
                            <x-button
                                icon="o-pencil"
                                class="btn-sm btn-ghost"
                                wire:click="editCourse({{ $course->id }})"
                                aria-label="ویرایش" />
                        </div>
                    @endscope
                </x-table>
            </div>
        </div>

        <div class="mt-4">
            {{ $this->courses->links() }}
        </div>
    @endif

    {{-- ── ایجاد دوره ──────────────────────────────────────────
         ورودی تاریخ فقط از JalaliDatePicker می‌آید؛ مقدار property همیشه
         میلادی ISO است و نمایش شمسی داخل خود کامپوننت انجام می‌شود. --}}
    <x-modal wire:model="showEditModal" title="ویرایش دوره" separator>
        <div class="space-y-5">
            <div class="grid gap-3 md:grid-cols-2">
                <x-input label="عنوان دوره" wire:model="new_title" error="new_title" />

                <x-input label="کد دوره" wire:model="new_code" error="new_code" />
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <x-select label="درس" wire:model="new_subject_id"
                          :options="$this->subjectOptions()"
                          option-value="id" option-label="name"
                          placeholder="انتخاب درس" error="new_subject_id" />

                <x-select label="استاد" wire:model="new_teacher_id"
                          :options="$this->teacherOptions()"
                          option-value="id" option-label="name"
                          placeholder="بعداً تعیین می‌شود" error="new_teacher_id" />
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <x-input label="ظرفیت" type="number" wire:model="new_capacity"
                         hint="۰ یعنی نامحدود" error="new_capacity" />

                <x-input label="تعداد جلسات" type="number" wire:model="new_session_count"
                         error="new_session_count" />

                <x-input label="مدت هر جلسه (دقیقه)" type="number" wire:model="new_session_duration"
                         error="new_session_duration" />
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <x-input label="شهریهٔ پایه (تومان)" type="number" wire:model="new_base_fee"
                         hint="۰ یعنی رایگان" error="new_base_fee" />

                <livewire:jalali-date-picker
                    wire:model.live="new_starts_at"
                    label="تاریخ شروع (شمسی)" />
            </div>

            <div class="modal-action">
                <x-button label="انصراف" class="btn-ghost" wire:click="cancelEdit" />
                <x-button label="ذخیرهٔ تغییرات" class="btn-primary" wire:click="updateCourse" />
            </div>
        </div>
    </x-modal>

    <x-modal wire:model="showCreateModal" title="ثبت دورهٔ جدید" separator>
        <div class="space-y-5">
            <div class="grid gap-3 md:grid-cols-2">
                <x-input label="عنوان دوره" wire:model="new_title"
                         placeholder="دورهٔ جمع‌بندی ریاضی" error="new_title" />

                <x-input label="کد دوره" wire:model="new_code"
                         placeholder="خالی بگذارید اگر هنوز ندارد" error="new_code" />
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <x-select label="درس" wire:model="new_subject_id"
                          :options="$this->subjectOptions()"
                          option-value="id" option-label="name"
                          placeholder="انتخاب درس" error="new_subject_id" />

                <x-select label="استاد" wire:model="new_teacher_id"
                          :options="$this->teacherOptions()"
                          option-value="id" option-label="name"
                          placeholder="بعداً تعیین می‌شود" error="new_teacher_id" />
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <x-input label="ظرفیت" type="number" wire:model="new_capacity"
                         hint="۰ یعنی نامحدود" error="new_capacity" />

                <x-input label="تعداد جلسات" type="number" wire:model="new_session_count"
                         error="new_session_count" />

                <x-input label="مدت هر جلسه (دقیقه)" type="number" wire:model="new_session_duration"
                         error="new_session_duration" />
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <x-input label="شهریهٔ پایه (تومان)" type="number" wire:model="new_base_fee"
                         hint="۰ یعنی رایگان" error="new_base_fee" />

                <livewire:jalali-date-picker
                    wire:model.live="new_starts_at"
                    label="تاریخ شروع (شمسی)" />
            </div>

            <div class="modal-action">
                <x-button label="انصراف" class="btn-ghost" wire:click="$set('showCreateModal', false)" />
                <x-button label="ثبت دوره" class="btn-primary" wire:click="createCourse" />
            </div>
        </div>
    </x-modal>
</div>
