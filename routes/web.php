<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Citizen\HomeController as CitizenHomeController;
use App\Http\Controllers\Ranger\HomeController as RangerHomeController;
use App\Support\RoleHome;
use Illuminate\Support\Facades\Route;

Route::get('/', function (RoleHome $roleHome) {
    if (auth()->check()) {
        return redirect($roleHome->url(auth()->user()));
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active', 'role:citizen'])->prefix('citizen')->name('citizen.')->group(function () {
    Route::get('/laporan', CitizenHomeController::class)->name('home');
});

Route::middleware(['auth', 'active', 'role:ranger'])->prefix('ranger')->name('ranger.')->group(function () {
    Route::get('/', RangerHomeController::class)->name('home');
});

Route::middleware(['auth', 'active', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
    Route::get('/pengguna/buat', [UserController::class, 'create'])->name('users.create');
    Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
    Route::get('/pengguna/{user}', [UserController::class, 'edit']);
    Route::get('/pengguna/{user}/ubah', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/pengguna/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/pengguna/{user}/pulihkan', [UserController::class, 'restore'])
        ->withTrashed()
        ->name('users.restore');
});
