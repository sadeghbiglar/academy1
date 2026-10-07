<?php

use Livewire\Component;
use App\Models\Student;
new class extends Component {
    public string $title = 'دانش‌آموزان آموزشگاه';
    public function getStudents()
    {
        return Student::all();
    }
    public function render()
{
    return view('components.⚡students')
        ->layout('layouts.academy');
}ّ
};
?>

<div>
     <h1>{{ $title }}</h1>

    <p>لیست دانش‌آموزان:</p>

    <ul>
        @foreach ($this->getStudents() as $student)
            <li>
                {{ $student->first_name }}
                {{ $student->last_name }}
                -
                {{ $student->mobile }}
            </li>
        @endforeach
    </ul>
</div>