<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Livewire\Admin\Auth\Login;
use App\Http\Livewire\Admin\Dashboard\Dashboard as AdminDashboard;
use App\Http\Livewire\Admin\Institutes\InstitutesComponent;
use App\Http\Livewire\Admin\Users\UsersComponent;
use App\Http\Livewire\Admin\Roles\RolesComponent;
use App\Http\Livewire\Admin\AuditTrail\AuditTrailComponent;
use App\Http\Livewire\Admin\Notifications\NotificationLogComponent;

Route::get('/', fn () => redirect()->route('admin.login'));
Route::get('/admin/login', Login::class)->name('admin.login')->middleware('guest');
Route::get('/admin', fn () => redirect()->route('admin.login'));

Route::get('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('admin.login');
})->name('admin.logout');

Route::prefix('admin')->middleware(['auth', 'active.user'])->group(function () {
    Route::get('/dashboard', AdminDashboard::class)->name('admin.dashboard');

    // Module 1 — Core / Foundation
    Route::get('/institutes', InstitutesComponent::class)->name('admin.institutes')->middleware('role:super-admin');
    Route::get('/institutes/{institute_id}/users', UsersComponent::class)->name('admin.institute-users.institute')->middleware('permission:users.view');
    Route::get('/users', UsersComponent::class)->name('admin.users')->middleware('permission:users.view');
    Route::get('/roles', RolesComponent::class)->name('admin.roles')->middleware('role:super-admin');
    Route::get('/audit-trail', AuditTrailComponent::class)->name('admin.audit-trail')->middleware('permission:audit.view');
    Route::get('/notifications', NotificationLogComponent::class)->name('admin.notifications')->middleware('permission:notifications.view');
});
