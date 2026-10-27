<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Master\CaseStatusController;
use App\Http\Controllers\Admin\Master\CheckpointController;
use App\Http\Controllers\Admin\Master\ForestAreaController;
use App\Http\Controllers\Admin\Master\IncidentTypeController;
use App\Http\Controllers\Admin\Master\RiskLevelController;
use App\Http\Controllers\Admin\PatrolController as AdminPatrolController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Citizen\HomeController as CitizenHomeController;
use App\Http\Controllers\Ranger\HomeController as RangerHomeController;
use App\Http\Controllers\Ranger\PatrolController as RangerPatrolController;
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
    Route::get('/patroli', [RangerPatrolController::class, 'index'])->name('patrols.index');
    Route::get('/patroli/{patrol}', [RangerPatrolController::class, 'show'])->name('patrols.show');
    Route::post('/patroli/{patrol}/mulai', [RangerPatrolController::class, 'start'])->name('patrols.start');
    Route::post('/patroli/{patrol}/akhiri', [RangerPatrolController::class, 'finish'])->name('patrols.finish');
    Route::get('/patroli/{patrol}/tracks', [RangerPatrolController::class, 'tracks'])->name('patrols.tracks.index');
    Route::post('/patroli/{patrol}/tracks', [RangerPatrolController::class, 'recordTrack'])->name('patrols.tracks.store');
});

Route::middleware(['auth', 'active', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/patroli', [AdminPatrolController::class, 'index'])->name('patrols.index');
    Route::post('/patroli', [AdminPatrolController::class, 'store'])->name('patrols.store');
    Route::get('/patroli/{patrol}', [AdminPatrolController::class, 'show'])->name('patrols.show');
    Route::get('/patroli/{patrol}/tracks', [AdminPatrolController::class, 'tracks'])->name('patrols.tracks.index');
    Route::get('/area-hutan/{area}/checkpoints', [AdminPatrolController::class, 'checkpoints'])->name('patrols.checkpoints');

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

    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.master.forest-areas.index'));

        // Kawasan Hutan
        Route::get('/kawasan', [ForestAreaController::class, 'index'])->name('forest-areas.index');
        Route::get('/kawasan/buat', [ForestAreaController::class, 'create'])->name('forest-areas.create');
        Route::post('/kawasan', [ForestAreaController::class, 'store'])->name('forest-areas.store');
        Route::get('/kawasan/{forest_area}', [ForestAreaController::class, 'edit']);
        Route::get('/kawasan/{forest_area}/ubah', [ForestAreaController::class, 'edit'])->name('forest-areas.edit');
        Route::put('/kawasan/{forest_area}', [ForestAreaController::class, 'update'])->name('forest-areas.update');
        Route::delete('/kawasan/{forest_area}', [ForestAreaController::class, 'destroy'])->name('forest-areas.destroy');
        Route::post('/kawasan/{forest_area}/pulihkan', [ForestAreaController::class, 'restore'])
            ->withTrashed()
            ->name('forest-areas.restore');

        // Checkpoint
        Route::get('/checkpoint', [CheckpointController::class, 'index'])->name('checkpoints.index');
        Route::get('/checkpoint/buat', [CheckpointController::class, 'create'])->name('checkpoints.create');
        Route::post('/checkpoint', [CheckpointController::class, 'store'])->name('checkpoints.store');
        Route::get('/checkpoint/{checkpoint}', [CheckpointController::class, 'edit']);
        Route::get('/checkpoint/{checkpoint}/ubah', [CheckpointController::class, 'edit'])->name('checkpoints.edit');
        Route::put('/checkpoint/{checkpoint}', [CheckpointController::class, 'update'])->name('checkpoints.update');
        Route::delete('/checkpoint/{checkpoint}', [CheckpointController::class, 'destroy'])->name('checkpoints.destroy');
        Route::post('/checkpoint/{checkpoint}/pulihkan', [CheckpointController::class, 'restore'])
            ->withTrashed()
            ->name('checkpoints.restore');

        // Tipe Insiden
        Route::get('/tipe-insiden', [IncidentTypeController::class, 'index'])->name('incident-types.index');
        Route::get('/tipe-insiden/buat', [IncidentTypeController::class, 'create'])->name('incident-types.create');
        Route::post('/tipe-insiden', [IncidentTypeController::class, 'store'])->name('incident-types.store');
        Route::get('/tipe-insiden/{incident_type}', [IncidentTypeController::class, 'edit']);
        Route::get('/tipe-insiden/{incident_type}/ubah', [IncidentTypeController::class, 'edit'])->name('incident-types.edit');
        Route::put('/tipe-insiden/{incident_type}', [IncidentTypeController::class, 'update'])->name('incident-types.update');
        Route::delete('/tipe-insiden/{incident_type}', [IncidentTypeController::class, 'destroy'])->name('incident-types.destroy');
        Route::post('/tipe-insiden/{incident_type}/pulihkan', [IncidentTypeController::class, 'restore'])
            ->withTrashed()
            ->name('incident-types.restore');

        // Tingkat Risiko
        Route::get('/risiko', [RiskLevelController::class, 'index'])->name('risk-levels.index');
        Route::get('/risiko/buat', [RiskLevelController::class, 'create'])->name('risk-levels.create');
        Route::post('/risiko', [RiskLevelController::class, 'store'])->name('risk-levels.store');
        Route::get('/risiko/{risk_level}', [RiskLevelController::class, 'edit']);
        Route::get('/risiko/{risk_level}/ubah', [RiskLevelController::class, 'edit'])->name('risk-levels.edit');
        Route::put('/risiko/{risk_level}', [RiskLevelController::class, 'update'])->name('risk-levels.update');
        Route::post('/risiko/{risk_level}/toggle', [RiskLevelController::class, 'toggle'])->name('risk-levels.toggle');

        // Status Kasus (Read-Only)
        Route::get('/status', [CaseStatusController::class, 'index'])->name('case-statuses.index');
    });
});
