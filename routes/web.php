<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::users.index');

Route::livewire('/students', 'pages::students.index');