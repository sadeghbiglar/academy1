<?php

use App\Models\Student;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

new #[Layout('layouts::academy')]
class extends Component {
    use Toast;
    use WithPagination;
    public string $title = 'دانش‌آموزان آموزشگاه';

    public string $search = '';

    // اطلاعات هویتی
    public string $first_name = '';
    public string $last_name = '';
    public string $national_code = '';
    public string $father_name = '';
    public string $birth_date = '';
    public string $gender = '';

    // اطلاعات تماس
    public string $mobile = '';
    public string $phone = '';
    public string $email = '';

    // اطلاعات سرپرست
    public string $guardian_name = '';
    public string $guardian_mobile = '';

    // اطلاعات محل سکونت
    public string $province = '';
    public string $city = '';
    public string $address = '';
    public string $postal_code = '';

    public bool $showCreateModal = false;

    // ویرایش دانش‌آموز
    public ?int $editingStudentId = null;
    public bool $showEditModal = false;

    // اطلاعات هویتی
    public string $edit_first_name = '';
    public string $edit_last_name = '';
    public string $edit_national_code = '';
    public string $edit_father_name = '';
    public string $edit_birth_date = '';
    public string $edit_gender = '';

    // اطلاعات تماس
    public string $edit_mobile = '';
    public string $edit_phone = '';
    public string $edit_email = '';

    // اطلاعات سرپرست
    public string $edit_guardian_name = '';
    public string $edit_guardian_mobile = '';

    // اطلاعات محل سکونت
    public string $edit_province = '';
    public string $edit_city = '';
    public string $edit_address = '';
    public string $edit_postal_code = '';

    public ?int $deletingStudentId = null;

    public bool $showDeleteModal = false;

    public ?int $viewingStudentId = null;
    public bool $showDetailsModal = false;

    public function viewStudent(int $id): void
    {
        $this->viewingStudentId = $id;

        $this->showDetailsModal = true;
    }
    protected function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:50'],
            'last_name' => ['required', 'string', 'min:2', 'max:50'],
            'national_code' => ['required', 'string', 'size:10'],
            'father_name' => ['required', 'string', 'min:2', 'max:50'],
            'birth_date' => [
                'required',
                'regex:/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}$/',
            ],
            'gender' => ['required', 'in:male,female'],

            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],

            'guardian_name' => ['required', 'string', 'min:2', 'max:100'],
            'guardian_mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],

            'province' => ['required', 'string', 'max:50'],
            'city' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:1000'],
            'postal_code' => ['nullable', 'string', 'size:10'],
        ];
    }
    public function saveStudent()
    {
        $this->validate();
        Student::create([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'national_code' => $this->national_code,
            'father_name' => $this->father_name,
            'birth_date' => miladi_date($this->birth_date),
            'gender' => $this->gender,

            'mobile' => $this->mobile,
            'phone' => $this->phone,
            'email' => $this->email,

            'guardian_name' => $this->guardian_name,
            'guardian_mobile' => $this->guardian_mobile,

            'province' => $this->province,
            'city' => $this->city,
            'address' => $this->address,
            'postal_code' => $this->postal_code,
        ]);
        $this->reset([
            'first_name',
            'last_name',
            'national_code',
            'father_name',
            'birth_date',
            'gender',
            'mobile',
            'phone',
            'email',
            'guardian_name',
            'guardian_mobile',
            'province',
            'city',
            'address',
            'postal_code',
        ]);
        $this->showCreateModal = false;
        $this->success(
            'ثبت موفق',
            'دانش‌آموز با موفقیت ثبت شد.'
        );
    }
    public function editStudent(int $id): void
    {
        $student = Student::findOrFail($id);

        $this->editingStudentId = $student->id;

        // اطلاعات هویتی
        $this->edit_first_name = $student->first_name;
        $this->edit_last_name = $student->last_name;
        $this->edit_national_code = $student->national_code ?? '';
        $this->edit_father_name = $student->father_name ?? '';
        $this->edit_birth_date = jalali_date($student->birth_date) ?? '';
        $this->edit_gender = $student->gender ?? '';

        // اطلاعات تماس
        $this->edit_mobile = $student->mobile;
        $this->edit_phone = $student->phone ?? '';
        $this->edit_email = $student->email ?? '';

        // اطلاعات سرپرست
        $this->edit_guardian_name = $student->guardian_name ?? '';
        $this->edit_guardian_mobile = $student->guardian_mobile ?? '';

        // اطلاعات محل سکونت
        $this->edit_province = $student->province ?? '';
        $this->edit_city = $student->city ?? '';
        $this->edit_address = $student->address ?? '';
        $this->edit_postal_code = $student->postal_code ?? '';

        $this->showEditModal = true;
    }
    public function updateStudent(): void
    {
        $this->validate([
            'edit_first_name' => ['required', 'string', 'min:2', 'max:50'],
            'edit_last_name' => ['required', 'string', 'min:2', 'max:50'],
            'edit_national_code' => ['required', 'string', 'size:10'],
            'edit_father_name' => ['required', 'string', 'min:2', 'max:50'],
            'edit_birth_date' => [
                'required',
                'regex:/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}$/',
            ],
            'edit_gender' => ['required', 'in:male,female'],

            'edit_mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],
            'edit_phone' => ['nullable', 'string', 'max:20'],
            'edit_email' => ['nullable', 'email', 'max:255'],

            'edit_guardian_name' => ['required', 'string', 'min:2', 'max:100'],
            'edit_guardian_mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],

            'edit_province' => ['required', 'string', 'max:50'],
            'edit_city' => ['required', 'string', 'max:50'],
            'edit_address' => ['required', 'string', 'max:1000'],
            'edit_postal_code' => ['nullable', 'string', 'size:10'],
        ]);

        $student = Student::findOrFail($this->editingStudentId);

        $student->update([
            'first_name' => $this->edit_first_name,
            'last_name' => $this->edit_last_name,
            'national_code' => $this->edit_national_code,
            'father_name' => $this->edit_father_name,
            'birth_date' => miladi_date($this->edit_birth_date),
            'gender' => $this->edit_gender,

            'mobile' => $this->edit_mobile,
            'phone' => $this->edit_phone,
            'email' => $this->edit_email,

            'guardian_name' => $this->edit_guardian_name,
            'guardian_mobile' => $this->edit_guardian_mobile,

            'province' => $this->edit_province,
            'city' => $this->edit_city,
            'address' => $this->edit_address,
            'postal_code' => $this->edit_postal_code,
        ]);

        $this->showEditModal = false;

        $this->resetEditForm();

        $this->success(
            'ویرایش موفق',
            'اطلاعات دانش‌آموز با موفقیت به‌روزرسانی شد.'
        );
    }
    public function resetEditForm(): void
    {
        $this->reset([
            'editingStudentId',

            'edit_first_name',
            'edit_last_name',
            'edit_national_code',
            'edit_father_name',
            'edit_birth_date',
            'edit_gender',

            'edit_mobile',
            'edit_phone',
            'edit_email',

            'edit_guardian_name',
            'edit_guardian_mobile',

            'edit_province',
            'edit_city',
            'edit_address',
            'edit_postal_code',
        ]);
    }
    public function confirmDelete(int $id): void
    {
        $this->deletingStudentId = $id;

        $this->showDeleteModal = true;
    }
    public function deleteStudent(): void
    {
        $student = Student::findOrFail($this->deletingStudentId);

        $student->delete();

        $this->showDeleteModal = false;

        $this->deletingStudentId = null;

        $this->success(
            'حذف موفق',
            'دانش‌آموز با موفقیت حذف شد.'
        );
    }
    public array $headers = [
        ['key' => 'row_number', 'label' => '#'],
        ['key' => 'full_name', 'label' => 'نام و نام خانوادگی'],
        ['key' => 'national_code', 'label' => 'کد ملی'],
        ['key' => 'gender', 'label' => 'جنسیت'],
        ['key' => 'mobile', 'label' => 'موبایل'],
        ['key' => 'birth_date', 'label' => 'تاریخ تولد'],
        ['key' => 'created_at', 'label' => 'تاریخ ثبت'],
    ];
    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    #[Computed]
    public function students()
    {
        $students = Student::query()
            ->where(function ($query) {
                $query
                    ->where('first_name', 'like', '%' . $this->search . '%')
                    ->orWhere('last_name', 'like', '%' . $this->search . '%')
                    ->orWhere('mobile', 'like', '%' . $this->search . '%');
            })
            ->paginate(10);

        $students->getCollection()->transform(
            function ($student, $index) use ($students) {
                $student->row_number = $students->firstItem() + $index;

                $student->full_name = "{$student->first_name} {$student->last_name}";

                return $student;
            }
        );

        return $students;
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
                مدیریت اطلاعات دانش‌آموزان آموزشگاه
            </p>
        </div>

        <x-button label="دانش‌آموز جدید" icon="o-plus" class="btn-primary" wire:click="$set('showCreateModal', true)" />

    </div>
    <x-modal wire:model="showCreateModal" title="ثبت دانش‌آموز جدید" separator>
        <div class="space-y-6">

            {{-- اطلاعات هویتی --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات هویتی
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <x-input
                        label="نام"
                        wire:model="first_name"
                        placeholder="مثلاً علی"
                        error="first_name" />

                    <x-input
                        label="نام خانوادگی"
                        wire:model="last_name"
                        placeholder="مثلاً رضایی"
                        error="last_name" />

                    <x-input
                        label="کد ملی"
                        wire:model="national_code"
                        placeholder="مثلاً 1234567890"
                        error="national_code" />

                    <x-input
                        label="نام پدر"
                        wire:model="father_name"
                        placeholder="مثلاً محمد"
                        error="father_name" />

                    <x-input
                        label="تاریخ تولد"
                        type="text"
                        wire:model="birth_date"
                        placeholder="1400/05/12"
                        error="birth_date" />
                    <x-select
                        label="جنسیت"
                        wire:model="gender"
                        :options="[
                    ['id' => 'male', 'name' => 'پسر'],
                    ['id' => 'female', 'name' => 'دختر'],
                ]"
                        option-value="id"
                        option-label="name"
                        placeholder="انتخاب جنسیت"
                        error="gender" />

                </div>
            </div>

            {{-- اطلاعات تماس --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات تماس
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <x-input
                        label="شماره موبایل"
                        wire:model="mobile"
                        placeholder="مثلاً 09123456789"
                        error="mobile" />

                    <x-input
                        label="تلفن ثابت"
                        wire:model="phone"
                        placeholder="مثلاً 02433445566"
                        error="phone" />

                    <x-input
                        label="ایمیل"
                        type="email"
                        wire:model="email"
                        placeholder="example@email.com"
                        error="email" />

                </div>
            </div>

            {{-- اطلاعات سرپرست --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات سرپرست
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <x-input
                        label="نام سرپرست"
                        wire:model="guardian_name"
                        placeholder="مثلاً محمد رضایی"
                        error="guardian_name" />

                    <x-input
                        label="موبایل سرپرست"
                        wire:model="guardian_mobile"
                        placeholder="مثلاً 09123456789"
                        error="guardian_mobile" />

                </div>
            </div>

            {{-- اطلاعات محل سکونت --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات محل سکونت
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <x-input
                        label="استان"
                        wire:model="province"
                        placeholder="مثلاً زنجان"
                        error="province" />

                    <x-input
                        label="شهر"
                        wire:model="city"
                        placeholder="مثلاً ابهر"
                        error="city" />

                    <x-input
                        label="کد پستی"
                        wire:model="postal_code"
                        placeholder="مثلاً 4513712345"
                        error="postal_code" />

                    <div class="md:col-span-2">
                        <x-textarea
                            label="آدرس"
                            wire:model="address"
                            placeholder="آدرس کامل محل سکونت"
                            error="address"
                            rows="3" />
                    </div>

                </div>
            </div>

        </div>

        <x-slot:actions>

            <x-button label="انصراف" wire:click="$set('showCreateModal', false)" />

            <x-button label="ثبت دانش‌آموز" icon="o-check" class="btn-primary" wire:click="saveStudent" wire:loading.attr="disabled"
                wire:target="saveStudent" />

        </x-slot:actions>

    </x-modal>
    <x-modal
        wire:model="showEditModal"
        title="ویرایش دانش‌آموز"
        separator>
        <div class="space-y-6">

            {{-- اطلاعات هویتی --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات هویتی
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <x-input
                        label="نام"
                        wire:model="edit_first_name"
                        placeholder="مثلاً علی"
                        error="edit_first_name" />

                    <x-input
                        label="نام خانوادگی"
                        wire:model="edit_last_name"
                        placeholder="مثلاً رضایی"
                        error="edit_last_name" />

                    <x-input
                        label="کد ملی"
                        wire:model="edit_national_code"
                        placeholder="مثلاً 1234567890"
                        error="edit_national_code" />

                    <x-input
                        label="نام پدر"
                        wire:model="edit_father_name"
                        placeholder="مثلاً محمد"
                        error="edit_father_name" />

                    <x-input
                        label="تاریخ تولد"
                        type="text"
                        wire:model="edit_birth_date"
                        placeholder="1400/05/12"
                        error="edit_birth_date" />

                    <x-select
                        label="جنسیت"
                        wire:model="edit_gender"
                        :options="[
                    ['id' => 'male', 'name' => 'پسر'],
                    ['id' => 'female', 'name' => 'دختر'],
                ]"
                        option-value="id"
                        option-label="name"
                        placeholder="انتخاب جنسیت"
                        error="edit_gender" />

                </div>
            </div>

            {{-- اطلاعات تماس --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات تماس
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <x-input
                        label="شماره موبایل"
                        wire:model="edit_mobile"
                        placeholder="مثلاً 09123456789"
                        error="edit_mobile" />

                    <x-input
                        label="تلفن ثابت"
                        wire:model="edit_phone"
                        placeholder="مثلاً 02433445566"
                        error="edit_phone" />

                    <x-input
                        label="ایمیل"
                        type="email"
                        wire:model="edit_email"
                        placeholder="example@email.com"
                        error="edit_email" />

                </div>
            </div>

            {{-- اطلاعات سرپرست --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات سرپرست
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <x-input
                        label="نام سرپرست"
                        wire:model="edit_guardian_name"
                        placeholder="مثلاً محمد رضایی"
                        error="edit_guardian_name" />

                    <x-input
                        label="موبایل سرپرست"
                        wire:model="edit_guardian_mobile"
                        placeholder="مثلاً 09123456789"
                        error="edit_guardian_mobile" />

                </div>
            </div>

            {{-- اطلاعات محل سکونت --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات محل سکونت
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <x-input
                        label="استان"
                        wire:model="edit_province"
                        placeholder="مثلاً زنجان"
                        error="edit_province" />

                    <x-input
                        label="شهر"
                        wire:model="edit_city"
                        placeholder="مثلاً ابهر"
                        error="edit_city" />

                    <x-input
                        label="کد پستی"
                        wire:model="edit_postal_code"
                        placeholder="مثلاً 4513712345"
                        error="edit_postal_code" />

                    <div class="md:col-span-2">
                        <x-textarea
                            label="آدرس"
                            wire:model="edit_address"
                            placeholder="آدرس کامل محل سکونت"
                            error="edit_address"
                            rows="3" />
                    </div>

                </div>
            </div>

        </div>

        <x-slot:actions>

            <x-button
                label="انصراف"
                wire:click="$set('showEditModal', false)" />

            <x-button
                label="ذخیره تغییرات"
                icon="o-check"
                class="btn-primary"
                wire:click="updateStudent"
                wire:loading.attr="disabled"
                wire:target="updateStudent" />

        </x-slot:actions>

    </x-modal>
    <x-modal
        wire:model="showDeleteModal"
        title="حذف دانش‌آموز"
        separator>
        <div class="py-4">
            <p class="text-base">
                آیا از حذف این دانش‌آموز مطمئن هستید؟
            </p>

            <p class="mt-2 text-sm text-gray-500">
                این عملیات قابل بازگشت نیست.
            </p>
        </div>

        <x-slot:actions>

            <x-button
                label="انصراف"
                wire:click="$set('showDeleteModal', false)" />

            <x-button
                label="بله، حذف شود"
                icon="o-trash"
                class="btn-error"
                wire:click="deleteStudent"
                wire:loading.attr="disabled"
                wire:target="deleteStudent" />

        </x-slot:actions>

    </x-modal>
    <x-modal
        wire:model="showDetailsModal"
        title="جزئیات دانش‌آموز"
        separator
        class="backdrop-blur">
        @php
        $student = $viewingStudentId
        ? \App\Models\Student::find($viewingStudentId)
        : null;
        @endphp

        @if ($student)
        <div class="space-y-6">

            {{-- اطلاعات هویتی --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات هویتی
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <span class="text-sm opacity-60">نام</span>
                        <p class="font-medium">
                            {{ $student->first_name }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">نام خانوادگی</span>
                        <p class="font-medium">
                            {{ $student->last_name }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">کد ملی</span>
                        <p class="font-medium">
                            {{ $student->national_code ?: '—' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">نام پدر</span>
                        <p class="font-medium">
                            {{ $student->father_name ?: '—' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">تاریخ تولد</span>
                        <p class="font-medium">
                            {{ jalali_date($student->birth_date) ?? '—' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">جنسیت</span>
                        <p class="font-medium">
                            @if ($student->gender === 'male')
                            پسر
                            @elseif ($student->gender === 'female')
                            دختر
                            @else
                            —
                            @endif
                        </p>
                    </div>

                </div>
            </div>

            {{-- اطلاعات تماس --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات تماس
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <span class="text-sm opacity-60">موبایل</span>
                        <p class="font-medium">
                            {{ $student->mobile ?: '—' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">تلفن ثابت</span>
                        <p class="font-medium">
                            {{ $student->phone ?: '—' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">ایمیل</span>
                        <p class="font-medium">
                            {{ $student->email ?: '—' }}
                        </p>
                    </div>

                </div>
            </div>

            {{-- اطلاعات سرپرست --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات سرپرست
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <span class="text-sm opacity-60">نام سرپرست</span>
                        <p class="font-medium">
                            {{ $student->guardian_name ?: '—' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">موبایل سرپرست</span>
                        <p class="font-medium">
                            {{ $student->guardian_mobile ?: '—' }}
                        </p>
                    </div>

                </div>
            </div>

            {{-- اطلاعات محل سکونت --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات محل سکونت
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <span class="text-sm opacity-60">استان</span>
                        <p class="font-medium">
                            {{ $student->province ?: '—' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">شهر</span>
                        <p class="font-medium">
                            {{ $student->city ?: '—' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">کد پستی</span>
                        <p class="font-medium">
                            {{ $student->postal_code ?: '—' }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <span class="text-sm opacity-60">آدرس</span>
                        <p class="font-medium leading-7">
                            {{ $student->address ?: '—' }}
                        </p>
                    </div>

                </div>
            </div>

            {{-- اطلاعات سیستم --}}
            <div>
                <h3 class="text-base font-bold mb-4">
                    اطلاعات ثبت
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <span class="text-sm opacity-60">تاریخ ثبت</span>
                        <p class="font-medium">
                            {{ jalali_date($student->created_at) ?? '—' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm opacity-60">آخرین ویرایش</span>
                        <p class="font-medium">
                            {{ jalali_date($student->updated_at) ?? '—' }}
                        </p>
                    </div>

                </div>
            </div>

        </div>
        @endif

        <x-slot:actions>
            <x-button
                label="بستن"
                wire:click="$set('showDetailsModal', false)" />
        </x-slot:actions>

    </x-modal>
    <x-alert title="مدیریت دانش‌آموزان" description="در این بخش می‌توانید اطلاعات دانش‌آموزان آموزشگاه را مدیریت کنید."
        icon="o-information-circle" class="mb-6" />

    <div class="mb-6">

        <x-input label="جستجوی دانش‌آموز" placeholder="نام، نام خانوادگی یا شماره موبایل..." icon="o-magnifying-glass"
            wire:model.live="search" />

    </div>

    <div class="bg-base-100 rounded-box shadow">

        @if ($this->students->isEmpty())
        <div class="bg-base-100 rounded-box shadow p-10 text-center">
            <div class="flex justify-center mb-4">
                <x-icon
                    name="o-magnifying-glass"
                    class="w-12 h-12 opacity-30" />
            </div>

            <h3 class="text-lg font-bold">
                دانش‌آموزی پیدا نشد
            </h3>

            <p class="text-sm opacity-60 mt-2">
                @if ($search)
                دانش‌آموزی با عبارت «{{ $search }}» پیدا نشد.
                @else
                هنوز هیچ دانش‌آموزی ثبت نشده است.
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
                    :rows="$this->students"
                    striped
                    hover>
                    @scope('cell_gender', $student)
                    @if ($student->gender === 'male')
                    پسر
                    @elseif ($student->gender === 'female')
                    دختر
                    @else
                    —
                    @endif
                    @endscope

                    @scope('cell_birth_date', $student)
                    {{ jalali_date($student->birth_date) ?? '—' }}
                    @endscope

                    @scope('cell_created_at', $student)
                    {{ jalali_date($student->created_at) ?? '—' }}
                    @endscope
                    @scope('actions', $student)
                    <div class="flex items-center gap-1">
                        <x-button
                            icon="o-eye"
                            class="btn-sm btn-ghost"
                            wire:click="viewStudent({{ $student->id }})"
                            aria-label="مشاهده" />
                        <x-button
                            icon="o-pencil"
                            class="btn-sm btn-ghost"
                            wire:click="editStudent({{ $student->id }})"
                            aria-label="ویرایش" />

                        <x-button
                            icon="o-trash"
                            class="btn-sm btn-ghost text-error"
                            wire:click="confirmDelete({{ $student->id }})"
                            aria-label="حذف" />
                    </div>
                    @endscope

                </x-table>
            </div>
        </div>

        <div class="mt-4">
            {{ $this->students->links() }}
        </div>
        @endif

    </div>

</div>