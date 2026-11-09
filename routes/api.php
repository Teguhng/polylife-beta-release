<?php

use App\Http\Controllers\Api\KeuanganBudgetController;
use App\Http\Controllers\Api\KeuanganStatistikController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AiWorkspaceController;
use App\Http\Controllers\Ai\AiChatController;
use App\Http\Controllers\Api\CatatanController;
use App\Http\Controllers\Api\JadwalController;
use App\Http\Controllers\Api\KeuanganController;
use App\Http\Controllers\Api\PengumumanController;
use App\Http\Controllers\Api\ProfileAvatarController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TodolistController;
use Illuminate\Support\Facades\Route;

$userWriteMiddleware = ['throttle:api-write', 'prevent-duplicate-write'];

Route::prefix('v1')->group(function () use ($userWriteMiddleware) {
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('api.v1.auth.register');

    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1')
        ->name('api.v1.auth.forgot-password');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('api.v1.auth.login');

    Route::middleware(['auth:sanctum', 'api-active', 'abilities:'.AuthController::MOBILE_API_ABILITY])->group(function () use ($userWriteMiddleware) {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll'])->name('api.v1.auth.logout-all');
        Route::patch('profile', [ProfileController::class, 'update'])->middleware($userWriteMiddleware)->name('api.v1.profile.update');
        Route::patch('profile/password', [ProfileController::class, 'updatePassword'])->middleware($userWriteMiddleware)->name('api.v1.profile.password.update');
        Route::delete('profile/account', [ProfileController::class, 'deleteAccount'])->middleware($userWriteMiddleware)->name('api.v1.profile.account.destroy');
        Route::post('profile/affiliation-request', [ProfileController::class, 'submitAffiliationRequest'])->middleware($userWriteMiddleware)->name('api.v1.profile.affiliation-request.store');
        Route::delete('profile/affiliation-request', [ProfileController::class, 'cancelAffiliationRequest'])->middleware($userWriteMiddleware)->name('api.v1.profile.affiliation-request.destroy');
        Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar'])->middleware($userWriteMiddleware)->name('api.v1.profile.avatar.store');
        Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar'])->middleware($userWriteMiddleware)->name('api.v1.profile.avatar.destroy');
        Route::get('profile/avatar', ProfileAvatarController::class)->name('api.v1.profile.avatar');

        // AI uses the same conversation/run engine as the web workspace, with
        // Sanctum authentication for mobile clients.
        Route::get('ai/sessions', [AiWorkspaceController::class, 'sessions'])->name('api.v1.ai.sessions.index');
        Route::get('ai/sessions/{session}', [AiWorkspaceController::class, 'messages'])->whereNumber('session')->name('api.v1.ai.sessions.messages');
        Route::post('ai/chat', [AiChatController::class, 'sendMessage'])
            ->middleware('throttle:ai-chat')
            ->name('api.v1.ai.chat');
        Route::get('ai/runs/{run}', [AiChatController::class, 'runStatus'])->whereNumber('run')->name('api.v1.ai.runs.show');

        Route::get('sync/pull', [SyncController::class, 'pull'])->name('api.v1.sync.pull');
        Route::post('sync/push', [SyncController::class, 'push'])
            ->middleware('throttle:api-write')
            ->name('api.v1.sync.push');

        Route::get('catatan', [CatatanController::class, 'index'])->name('api.v1.catatan.index');
        Route::post('catatan', [CatatanController::class, 'store'])->middleware($userWriteMiddleware)->name('api.v1.catatan.store');
        Route::get('catatan/trash', [CatatanController::class, 'trash'])->name('api.v1.catatan.trash');
        Route::get('catatan/{catatan}', [CatatanController::class, 'show'])
            ->whereNumber('catatan')
            ->name('api.v1.catatan.show');
        Route::match(['put', 'patch'], 'catatan/{catatan}', [CatatanController::class, 'update'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('catatan')
            ->name('api.v1.catatan.update');
        Route::delete('catatan/{catatan}', [CatatanController::class, 'destroy'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('catatan')
            ->name('api.v1.catatan.destroy');
        Route::patch('catatan/{catatan}/restore', [CatatanController::class, 'restore'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('catatan')
            ->name('api.v1.catatan.restore');
        Route::delete('catatan/{catatan}/force-delete', [CatatanController::class, 'forceDelete'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('catatan')
            ->name('api.v1.catatan.force-delete');

        Route::get('pengumuman', [PengumumanController::class, 'index'])->name('api.v1.pengumuman.index');
        Route::get('pengumuman/{broadcast}', [PengumumanController::class, 'show'])
            ->whereNumber('broadcast')
            ->name('api.v1.pengumuman.show');
        Route::get('reminder', [ReminderController::class, 'index'])->name('api.v1.reminder.index');
        Route::get('reminder/options', [ReminderController::class, 'options'])->name('api.v1.reminder.options');
        Route::get('reminder/next', [ReminderController::class, 'next'])->name('api.v1.reminder.next');
        Route::post('reminder', [ReminderController::class, 'store'])->middleware($userWriteMiddleware)->name('api.v1.reminder.store');
        Route::delete('reminder/{reminder}', [ReminderController::class, 'destroy'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('reminder')
            ->name('api.v1.reminder.destroy');

        Route::get('todolist', [TodolistController::class, 'index'])->name('api.v1.todolist.index');
        Route::post('todolist', [TodolistController::class, 'store'])->middleware($userWriteMiddleware)->name('api.v1.todolist.store');
        Route::match(['put', 'patch'], 'todolist/{todolist}', [TodolistController::class, 'update'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('todolist')
            ->name('api.v1.todolist.update');
        Route::delete('todolist/{todolist}', [TodolistController::class, 'destroy'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('todolist')
    ->name('api.v1.todolist.destroy');

        Route::get('keuangan', [KeuanganController::class, 'index'])->name('api.v1.keuangan.index');
        Route::post('keuangan', [KeuanganController::class, 'store'])->middleware($userWriteMiddleware)->name('api.v1.keuangan.store');
        Route::get('keuangan/{keuangan}', [KeuanganController::class, 'show'])
            ->whereNumber('keuangan')
            ->name('api.v1.keuangan.show');
        Route::match(['put', 'patch'], 'keuangan/{keuangan}', [KeuanganController::class, 'update'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('keuangan')
            ->name('api.v1.keuangan.update');
        Route::delete('keuangan/{keuangan}', [KeuanganController::class, 'destroy'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('keuangan')
            ->name('api.v1.keuangan.destroy');
        // Anggaran Keuangan
        Route::get('keuangan/anggaran', [KeuanganBudgetController::class, 'index'])
            ->name('api.v1.keuangan.anggaran.index');

        Route::post('keuangan/anggaran', [KeuanganBudgetController::class, 'store'])
            // This endpoint is an idempotent upsert by user, category, and period.
            ->middleware('throttle:api-write')
            ->name('api.v1.keuangan.anggaran.store');

        Route::delete('keuangan/anggaran/{budget}', [KeuanganBudgetController::class, 'destroy'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('budget')
            ->name('api.v1.keuangan.anggaran.destroy');

        // Statistik Keuangan
        Route::get('keuangan/statistik', [KeuanganStatistikController::class, 'index'])
            ->name('api.v1.keuangan.statistik.index');

        Route::get('jadwal', [JadwalController::class, 'index'])->name('api.v1.jadwal.index');
        Route::post('jadwal', [JadwalController::class, 'store'])->middleware($userWriteMiddleware)->name('api.v1.jadwal.store');
        Route::get('jadwal/{jadwal}', [JadwalController::class, 'show'])
            ->whereNumber('jadwal')
            ->name('api.v1.jadwal.show');
        Route::match(['put', 'patch'], 'jadwal/{jadwal}', [JadwalController::class, 'update'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('jadwal')
            ->name('api.v1.jadwal.update');
        Route::delete('jadwal/{jadwal}', [JadwalController::class, 'destroy'])
            ->middleware($userWriteMiddleware)
            ->whereNumber('jadwal')
            ->name('api.v1.jadwal.destroy');
    });
});
