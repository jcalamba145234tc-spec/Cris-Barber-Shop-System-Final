<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarberArchiveController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EarningClaimController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionRecycleBinController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});
Route::middleware(['auth', 'role:admin,barber'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/earnings', DashboardController::class)->middleware('role:barber')->name('earnings');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::middleware('role:admin')->group(function () {
        Route::get('/transactions/recycle-bin', [TransactionRecycleBinController::class, 'index'])->name('transactions.recycle-bin');
        Route::get('/transactions/recycle-bin/{transaction}', [TransactionRecycleBinController::class, 'show'])->name('transactions.archived-show');
        Route::post('/transactions/recycle-bin/{transaction}/restore', [TransactionRecycleBinController::class, 'restore'])->name('transactions.restore');
    });
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::patch('/transactions/{transaction}/status', [TransactionController::class, 'status'])->name('transactions.status');
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/clock', [AttendanceController::class, 'clock'])->middleware('role:barber')->name('attendance.clock');

    Route::middleware('role:admin')->group(function () {
        Route::get('/pos', [TransactionController::class, 'create'])->name('pos');
        Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('/transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
        Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
        Route::resource('services', ServiceController::class);
        Route::resource('inventory', InventoryController::class)->parameters(['inventory' => 'product']);
        Route::patch('/inventory/{product}/status', [InventoryController::class, 'updateStatus'])->name('inventory.status');
        Route::post('/inventory/{product}/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
        Route::get('/customers/recycle-bin', [CustomerController::class, 'recycleBin'])->name('customers.recycle-bin');
        Route::post('/customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
        Route::delete('/customers/{customer}/permanent', [CustomerController::class, 'forceDelete'])->name('customers.permanent');
        Route::resource('customers', CustomerController::class);
        Route::get('/earning-claims', [EarningClaimController::class, 'index'])->name('earning-claims.index');
        Route::post('/earning-claims/{barber}', [EarningClaimController::class, 'store'])->name('earning-claims.store');
        Route::get('/accounts/email-preview', [AuthController::class, 'emailPreview'])->name('accounts.email-preview');
        Route::get('/barbers/archived', [BarberArchiveController::class, 'index'])->name('barbers.archived');
        Route::post('/barbers/{barber}/archive', [BarberArchiveController::class, 'archive'])->name('barbers.archive');
        Route::post('/barbers/{barber}/restore', [BarberArchiveController::class, 'restore'])->name('barbers.restore');
        Route::get('/barbers/{barber}/edit', [BarberController::class, 'edit'])->name('barbers.edit');
        Route::put('/barbers/{barber}', [BarberController::class, 'update'])->name('barbers.update');
        Route::get('/barbers', [BarberController::class, 'index'])->name('barbers.index');
        Route::patch('/barbers/{barber}/status', [BarberController::class, 'updateStatus'])->name('barbers.status');
        Route::post('/barbers/{barber}/reset-password', [BarberController::class, 'resetPassword'])->name('barbers.password');
        Route::get('/accounts/create', [AuthController::class, 'showRegister'])->name('accounts.create');
        Route::post('/accounts', [AuthController::class, 'register'])->name('accounts.store');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
        Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
        Route::get('/reports/csv', [ReportController::class, 'csv'])->name('reports.csv');
    });
});
