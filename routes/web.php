<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Livewire\Admin\Auth\Login;
use App\Http\Livewire\Admin\Dashboard\Dashboard as AdminDashboard;
use App\Http\Livewire\Admin\Institutes\InstitutesComponent;

Route::get('/', fn () => redirect()->route('admin.login'));
Route::get('/admin/login', Login::class)->name('admin.login')->middleware('guest');
Route::get('/admin', fn () => redirect()->route('admin.login'));

Route::get('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('admin.login');
})->name('admin.logout');

Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', AdminDashboard::class)->name('admin.dashboard');
    Route::get('/institutes', InstitutesComponent::class)->name('admin.institutes')->middleware('role:super-admin');
});


