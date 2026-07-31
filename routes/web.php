<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
    Route::post('/auth/telegram', [AuthController::class, 'telegram'])->name('auth.telegram');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::post('/tasks/{task}/make-root', [TaskController::class, 'makeRoot'])->name('tasks.make-root');
    Route::post('/tasks/{task}/close', [TaskController::class, 'close'])->name('tasks.close');
    Route::post('/tasks/{task}/events', [TaskController::class, 'linkEvent'])->name('tasks.link-event');
    Route::delete('/tasks/{task}/events/{event}', [TaskController::class, 'unlinkEvent'])->name('tasks.unlink-event');
    Route::post('/tasks/{task}/attachments', [TaskController::class, 'storeAttachment'])->name('tasks.attachments.store');
    Route::delete('/tasks/{task}/attachments/{attachment}', [TaskController::class, 'destroyAttachment'])->name('tasks.attachments.destroy');

    Route::get('/calendar', [EventController::class, 'index'])->name('calendar.index');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::post('/advances', [FinanceController::class, 'storeAdvance'])->name('advances.store');
    Route::put('/advances/{advance}', [FinanceController::class, 'updateAdvance'])->name('advances.update');
    Route::post('/advances/{advance}/return', [FinanceController::class, 'returnRemainder'])->name('advances.return');
    Route::post('/advances/{advance}/zero', [FinanceController::class, 'zeroUnknown'])->name('advances.zero');
    Route::post('/advances/{advance}/overspend', [FinanceController::class, 'overspend'])->name('advances.overspend');
    Route::post('/advances/{advance}/settle', [FinanceController::class, 'settle'])->name('advances.settle');
    Route::post('/advances/{advance}/expenses', [FinanceController::class, 'storeExpense'])->name('advances.expenses.store');
    Route::post('/advances/{advance}/expenses/{expense}/receipts', [FinanceController::class, 'storeReceipt'])->name('advances.receipts.store');

    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('/settings/dictionaries/{key}', [SettingsController::class, 'storeDict'])->name('settings.dict.store');
    Route::put('/settings/dictionaries/{key}/{slug}', [SettingsController::class, 'updateDict'])->name('settings.dict.update');
    Route::delete('/settings/dictionaries/{key}/{slug}', [SettingsController::class, 'destroyDict'])->name('settings.dict.destroy');
});
