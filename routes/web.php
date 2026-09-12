<?php

declare(strict_types=1);

use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\SuperAdmin\AdminAccountController;
use App\Http\Controllers\Web\SuperAdmin\AuditLogController;
use App\Http\Controllers\Web\SuperAdmin\AuthController;
use App\Http\Controllers\Web\SuperAdmin\DashboardController;
use App\Http\Controllers\Web\SuperAdmin\PaymentController;
use App\Http\Controllers\Web\SuperAdmin\UserController;
use App\Http\Controllers\Web\SuperAdmin\VerificationController;
use App\Http\Controllers\Web\SuperAdmin\WorkerController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Reset kata sandi pengguna lewat tautan sekali pakai dari email.
 *
 * Tautan kedaluwarsa oleh DUA kondisi: lewat 60 menit (standar broker
 * `auth.passwords.users.expire`) atau sudah terpakai (token dihapus saat
 * reset berhasil). Keduanya menampilkan halaman `expired` yang sama.
 *
 * `success` didaftarkan SEBELUM `{token}` supaya tidak ditangkap sebagai
 * token. Nama `password.reset` dipakai notifikasi email untuk membangun URL.
 */
Route::get('reset-password/success', [PasswordResetController::class, 'success'])
    ->name('password.reset.success');
Route::get('reset-password/{token}', [PasswordResetController::class, 'show'])
    ->name('password.reset');
Route::post('reset-password', [PasswordResetController::class, 'store'])
    ->middleware('throttle:reset')->name('password.reset.store');

/*
 * Dasbor pengelola super_admin (server-rendered, sesi `admin_web`).
 *
 * URL publik: sekarya.com/access/super_admin
 * Hanya peran super_admin (lihat EnsureSuperAdminWeb). Seluruh keputusan
 * bisnis tetap di Action yang sama dengan API — controller web hanya
 * memanggilnya, tidak menulis aturan sendiri.
 */
Route::prefix('access/super_admin')->name('super_admin.')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login')->name('login.attempt');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(['super_admin.web'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('verifications', [VerificationController::class, 'index'])->name('verifications.index');
        Route::get('verifications/{verification}', [VerificationController::class, 'show'])->name('verifications.show');
        Route::post('verifications/{verification}/approve', [VerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('verifications/{verification}/reject', [VerificationController::class, 'reject'])->name('verifications.reject');
        Route::post('verifications/{verification}/revoke', [VerificationController::class, 'revoke'])->name('verifications.revoke');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm'])->name('payments.confirm');
        Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
        Route::post('users/{user}/reinstate', [UserController::class, 'reinstate'])->name('users.reinstate');

        Route::get('workers', [WorkerController::class, 'index'])->name('workers.index');
        Route::get('workers/{worker}', [WorkerController::class, 'show'])->name('workers.show');

        Route::get('admins', [AdminAccountController::class, 'index'])->name('admins.index');
        Route::post('admins', [AdminAccountController::class, 'store'])->name('admins.store');
        Route::delete('admins/{admin}', [AdminAccountController::class, 'destroy'])->name('admins.destroy');

        Route::get('audit-logs', AuditLogController::class)->name('audit.index');
    });
});

/*
 * API documentation, served straight out of docs/ — which sits outside public/,
 * so it is not otherwise reachable over HTTP.
 *
 * Registered only outside production: the spec describes every endpoint and error
 * code, and that is not something to hand out publicly by default. Delete the guard
 * deliberately if you ever decide the docs should be public.
 */
if (! app()->isProduction()) {
    Route::prefix('docs')->name('docs.')->group(function (): void {
        Route::get('/', function () {
            $html = base_path('docs/api.html');

            abort_unless(is_file($html), Response::HTTP_NOT_FOUND, <<<'TXT'
                docs/api.html has not been built yet. Run:
                npx --yes -p @redocly/cli redocly build-docs docs/openapi.yaml -o docs/api.html
                TXT);

            return response()->file($html);
        })->name('index');

        // The raw spec, for linters, client generators and IDE plugins.
        Route::get('openapi.yaml', function () {
            $spec = base_path('docs/openapi.yaml');

            abort_unless(is_file($spec), Response::HTTP_NOT_FOUND, 'docs/openapi.yaml is missing.');

            return response()->file($spec, ['Content-Type' => 'application/yaml; charset=utf-8']);
        })->name('spec');

        // The URL people naturally type after reading about docs/api.html.
        Route::get('api.html', fn () => redirect()->route('docs.index'));
    });
}
