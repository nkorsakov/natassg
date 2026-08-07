<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\PublicFinanceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
    Route::post('/auth/telegram', [AuthController::class, 'telegram'])->name('auth.telegram');
});

Route::get('/r/{token}', [ReportController::class, 'show'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('reports.public');
Route::post('/r/{token}/accept', [ReportController::class, 'accept'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('reports.accept');

Route::get('/cashflow', [PublicFinanceController::class, 'show'])->name('cashflow.public');
Route::post('/cashflow/unlock', [PublicFinanceController::class, 'unlock'])->name('cashflow.unlock');
Route::post('/cashflow/lock', [PublicFinanceController::class, 'lock'])->name('cashflow.lock');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/tasks/{task}/make-root', [TaskController::class, 'makeRoot'])->name('tasks.make-root');
    Route::post('/tasks/{task}/close', [TaskController::class, 'close'])->name('tasks.close');
    Route::post('/tasks/{task}/events', [TaskController::class, 'linkEvent'])->name('tasks.link-event');
    Route::delete('/tasks/{task}/events/{event}', [TaskController::class, 'unlinkEvent'])->name('tasks.unlink-event');
    Route::post('/tasks/{task}/attachments', [TaskController::class, 'storeAttachment'])->name('tasks.attachments.store');
    Route::delete('/tasks/{task}/attachments/{attachment}', [TaskController::class, 'destroyAttachment'])->name('tasks.attachments.destroy');
    Route::post('/tasks/{task}/reminders', [ReminderController::class, 'store'])->name('tasks.reminders.store');
    Route::delete('/tasks/{task}/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('tasks.reminders.destroy');
    Route::post('/tasks/{task}/comments', [CommentController::class, 'storeForTask'])->name('tasks.comments.store');
    Route::put('/tasks/{task}/comments/{comment}', [CommentController::class, 'updateForTask'])->name('tasks.comments.update');
    Route::delete('/tasks/{task}/comments/{comment}', [CommentController::class, 'destroyForTask'])->name('tasks.comments.destroy');

    Route::get('/calendar', [EventController::class, 'index'])->name('calendar.index');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::post('/wallet/topups', [FinanceController::class, 'topUp'])->name('wallet.topups');
    Route::put('/wallet/topups/{transaction}', [FinanceController::class, 'updateTopUp'])->name('wallet.topups.update');
    Route::delete('/wallet/transactions/{transaction}', [FinanceController::class, 'destroyTransaction'])->name('wallet.transactions.destroy');
    Route::post('/advances', [FinanceController::class, 'storeAdvance'])->name('advances.store');
    Route::put('/advances/{advance}', [FinanceController::class, 'updateAdvance'])->name('advances.update');
    Route::delete('/advances/{advance}', [FinanceController::class, 'destroyAdvance'])->name('advances.destroy');
    Route::post('/advances/{advance}/approve', [FinanceController::class, 'approveAdvance'])->name('advances.approve');
    Route::post('/advances/{advance}/receive', [FinanceController::class, 'receiveAdvance'])->name('advances.receive');
    Route::post('/advances/{advance}/close-to-wallet', [FinanceController::class, 'closeToWallet'])->name('advances.close-to-wallet');
    Route::post('/advances/{advance}/expenses', [FinanceController::class, 'storeExpense'])->name('advances.expenses.store');
    Route::post('/advances/{advance}/expenses/{expense}/attach', [FinanceController::class, 'attachExpense'])->name('advances.expenses.attach');
    Route::post('/advances/{advance}/expenses/{expense}/detach', [FinanceController::class, 'detachExpense'])->name('advances.expenses.detach');
    Route::post('/advances/{advance}/expenses/{expense}/receipts', [FinanceController::class, 'storeAdvanceReceipt'])->name('advances.receipts.store');
    Route::post('/expenses', [FinanceController::class, 'storeFreeExpense'])->name('expenses.store');
    Route::put('/expenses/{expense}', [FinanceController::class, 'updateExpense'])->name('expenses.update');
    Route::delete('/expenses/{expense}', [FinanceController::class, 'destroyExpense'])->name('expenses.destroy');
    Route::post('/expenses/{expense}/receipts', [FinanceController::class, 'storeReceipt'])->name('expenses.receipts.store');
    Route::delete('/expenses/{expense}/receipts/{receipt}', [FinanceController::class, 'destroyReceipt'])->name('expenses.receipts.destroy');

    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('/settings/users', [SettingsController::class, 'storeUser'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [SettingsController::class, 'updateUser'])->name('settings.users.update');
    Route::post('/settings/dictionaries/{key}', [SettingsController::class, 'storeDict'])->name('settings.dict.store');
    Route::put('/settings/dictionaries/{key}/{slug}', [SettingsController::class, 'updateDict'])->name('settings.dict.update');
    Route::delete('/settings/dictionaries/{key}/{slug}', [SettingsController::class, 'destroyDict'])->name('settings.dict.destroy');
    Route::post('/settings/suppliers', [SettingsController::class, 'storeSupplier'])->name('settings.suppliers.store');
    Route::put('/settings/suppliers/{supplier}', [SettingsController::class, 'updateSupplier'])->name('settings.suppliers.update');
    Route::delete('/settings/suppliers/{supplier}', [SettingsController::class, 'destroySupplier'])->name('settings.suppliers.destroy');
});
