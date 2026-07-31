<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/login');

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard/Index');
})->name('dashboard');

Route::get('/tasks', function () {
    return Inertia::render('Tasks/Index');
})->name('tasks.index');

Route::get('/calendar', function () {
    return Inertia::render('Calendar/Index');
})->name('calendar.index');

Route::get('/finance', function () {
    return Inertia::render('Finance/Index');
})->name('finance.index');

Route::get('/contacts', function () {
    return Inertia::render('Contacts/Index');
})->name('contacts.index');

Route::get('/settings', function () {
    return Inertia::render('Settings/Index');
})->name('settings.index');
