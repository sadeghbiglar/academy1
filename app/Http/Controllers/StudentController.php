<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StudentController extends Controller
{
public function index()
{
    $students = [
        [
            'name' => 'علی رضایی',
            'phone' => '09120000000',
        ],
        [
            'name' => 'سارا احمدی',
            'phone' => '09121111111',
        ],
        [
            'name' => 'محمد کریمی',
            'phone' => '09122222222',
        ],
    ];

    return view('students.index', [
        'students' => $students,
    ]);
}
}
