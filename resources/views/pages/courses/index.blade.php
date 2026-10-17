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
                                icon="o-eye"
                                class="btn-sm btn-ghost"
                                wire:click="viewCourse({{ $course->id }})"
                                aria-label="مشاهده" />

                            <x-button
                                icon="o-pencil"
                                class="btn-sm btn-ghost"
                                wire:click="editCourse({{ $course->id }})"
                                aria-label="ویرایش" />

                            <x-button
                                icon="o-trash"
                                class="btn-sm btn-ghost text-error"
                                wire:click="confirmDelete({{ $course->id }})"
                                aria-label="حذف" />
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
    {{-- جزئیات دوره: هویت، زمان‌بندی، مالی. --}}
    <x-modal wire:model="showViewModal" :title="$this->viewedCourse?->title ?? 'جزئیات دوره'" separator>
        @if ($course = $this->viewedCourse)
            <div class="space-y-6">
                <div>
                    <h3 class="text-base font-bold mb-3">هویت دوره</h3>
                    <div class="grid gap-2 md:grid-cols-2">
                        <p><span class="opacity-60">عنوان:</span> {{ $course->title }}</p>
                        <p><span class="opacity-60">کد:</span> {{ $course->code ?? '—' }}</p>
                        <p><span class="opacity-60">درس:</span> {{ $course->subject?->name ?? '—' }}</p>
                        <p>
                            <span class="opacity-60">استاد:</span>
                            {{ $course->teacher ? $course->teacher->first_name.' '.$course->teacher->last_name : 'تعیین نشده' }}
                        </p>
                        <p>
                            <span class="opacity-60">وضعیت:</span>
                            <span class="badge badge-sm {{ $course->status?->color() }}">{{ $course->statusLabel() }}</span>
                        </p>
                        @if ($course->description)
                            <p class="md:col-span-2">
                                <span class="opacity-60">توضیحات:</span> {{ $course->description }}
                            </p>
                        @endif
                    </div>
                </div>

                <div>
                    <h3 class="text-base font-bold mb-3">زمان‌بندی</h3>
                    <div class="grid gap-2 md:grid-cols-3">
                        <p><span class="opacity-60">شروع:</span> {{ $course->startsAtJalali() ?? '—' }}</p>
                        <p><span class="opacity-60">تعداد جلسات:</span> {{ to_persian_digits($course->session_count) }}</p>
                        <p><span class="opacity-60">مدت هر جلسه:</span> {{ to_persian_digits($course->session_duration) }} دقیقه</p>
                        <p class="md:col-span-3">
                            <span class="opacity-60">مدت کل:</span>
                            {{ to_persian_digits(number_format($course->totalHours(), 1)) }} ساعت
                        </p>
                    </div>
                </div>

                <div>
                    <h3 class="text-base font-bold mb-3">ظرفیت و ثبت‌نام</h3>
                    <div class="grid gap-2 md:grid-cols-3">
                        <p>
                            <span class="opacity-60">ظرفیت:</span>
                            @if ($course->capacity === 0)
                                <span class="text-xs opacity-60">نامحدود</span>
                            @else
                                {{ to_persian_digits($course->capacity) }}
                            @endif
                        </p>
                        <p><span class="opacity-60">ثبت‌نام قطعی:</span> {{ to_persian_digits($course->enrolledCount()) }}</p>
                        <p>
                            <span class="opacity-60">جای خالی:</span>
                            {{ $course->freeSeats() === null ? 'نامحدود' : to_persian_digits($course->freeSeats()) }}
                        </p>
                    </div>

                    @if ($course->enrollments->isNotEmpty())
                        <div class="mt-4 overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>دانش‌آموز</th>
                                        <th>وضعیت</th>
                                        <th>شهریهٔ نهایی</th>
                                        <th>تاریخ ثبت‌نام</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($course->enrollments as $enrollment)
                                        <tr>
                                            <td>
                                                {{ $enrollment->student?->first_name }} {{ $enrollment->student?->last_name }}
                                            </td>
                                            <td>
                                                <span class="badge badge-sm {{ $enrollment->status?->color() }}">
                                                    {{ $enrollment->statusLabel() }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($enrollment->final_fee === null)
                                                    <span class="opacity-60">تعیین نشده</span>
                                                @else
                                                    {{ to_persian_digits(number_format($enrollment->final_fee / 10)) }} تومان
                                                @endif
                                            </td>
                                            <td>{{ $enrollment->enrolledAtJalali() ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm opacity-60 mt-2">هنوز کسی در این دوره ثبت‌نام نکرده است.</p>
                    @endif
                </div>

                <div>
                    <h3 class="text-base font-bold mb-3">مالی</h3>
                    <div class="grid gap-2 md:grid-cols-2">
                        <p>
                            <span class="opacity-60">شهریهٔ پایه:</span>
                            @if ($course->base_fee === 0)
                                <span class="badge badge-ghost badge-sm">رایگان</span>
                            @else
                                {{ to_persian_digits(number_format($course->base_fee / 10)) }} تومان
                            @endif
                        </p>
                        <p>
                            <span class="opacity-60">ظرفیت سالانهٔ درس:</span>
                            @if ($course->subject?->remainingHours() === null)
                                محدودیت ندارد
                            @else
                                {{ to_persian_digits($course->subject->remainingHours()) }} ساعت باقی‌مانده
                            @endif
                        </p>
                    </div>
                </div>

                <div class="modal-action">
                    <x-button label="بستن" class="btn-ghost" wire:click="closeView" />
                </div>
            </div>
        @endif
    </x-modal>

    {{-- تأیید حذف. اگر دوره ثبت‌نام قطعی دارد، حذف انجام نمی‌شود: آن ثبت‌نام‌ها
         مبنای فاکتور و کارنامه‌اند. بایگانی کردن راه برگشت‌پذیر است. --}}
    <x-modal wire:model="showDeleteModal" title="حذف دوره" separator>
        <div class="space-y-4">
            <p>
                آیا از حذف دورهٔ
                <span class="font-bold">{{ $deletingCourseTitle }}</span>
                مطمئن هستید؟
            </p>

            @if ($deletingCourseEnrollments > 0)
                <div class="alert alert-warning">
                    این دوره
                    <span class="font-bold">{{ to_persian_digits($deletingCourseEnrollments) }}</span>
                    ثبت‌نام قطعی دارد. حذف آن‌ها سابقهٔ مالی و کارنامه‌ای را که
                    هنوز ساخته نشده از بین می‌برد، پس حذف انجام نمی‌شود.
                    <span class="font-bold">بایگانی</span> کنید تا از فهرست‌ها بیرون
                    برود ولی داده‌اش بماند.
                </div>
            @else

                <p class="text-sm opacity-70">
                    ثبت‌نام‌های لغو شده هم با حذف پاک می‌شوند.
                </p>

                <div class="modal-action">
                    <x-button label="انصراف" class="btn-ghost" wire:click="cancelDelete" />
                    <x-button
                        label="حذف دوره"
                        class="btn-error"
                        wire:click="deleteCourse" />
                </div>
            @endif
        </div>
    </x-modal>

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
