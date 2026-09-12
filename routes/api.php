<?php

declare(strict_types=1);

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\Activity\ApproveActivityController;
use App\Http\Controllers\Api\V1\Activity\ListMyActivitiesController;
use App\Http\Controllers\Api\V1\Activity\RejectActivityController;
use App\Http\Controllers\Api\V1\Activity\ShowActivityController;
use App\Http\Controllers\Api\V1\Activity\StartActivityController;
use App\Http\Controllers\Api\V1\Activity\SubmitActivityController;
use App\Http\Controllers\Api\V1\Admin\Access\CreateAdminController;
use App\Http\Controllers\Api\V1\Admin\Access\DeleteAdminController;
use App\Http\Controllers\Api\V1\Admin\Access\ListAdminsController;
use App\Http\Controllers\Api\V1\Admin\Access\ShowAdminController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminLogoutController;
use App\Http\Controllers\Api\V1\Admin\Auth\AdminRefreshTokenController;
use App\Http\Controllers\Api\V1\Admin\Auth\ShowAdminMeController;
use App\Http\Controllers\Api\V1\Admin\Payment\ConfirmPaymentController;
use App\Http\Controllers\Api\V1\Admin\Payment\ListPaymentQueueController;
use App\Http\Controllers\Api\V1\Admin\Payment\RejectPaymentController;
use App\Http\Controllers\Api\V1\Admin\Payment\ShowPaymentController;
use App\Http\Controllers\Api\V1\Admin\User\BanUserController;
use App\Http\Controllers\Api\V1\Admin\User\ListUsersController;
use App\Http\Controllers\Api\V1\Admin\User\ReinstateUserController;
use App\Http\Controllers\Api\V1\Admin\User\ShowUserController;
use App\Http\Controllers\Api\V1\Admin\User\SuspendUserController;
use App\Http\Controllers\Api\V1\Admin\Verification\ApproveVerificationController;
use App\Http\Controllers\Api\V1\Admin\Verification\ListVerificationQueueController;
use App\Http\Controllers\Api\V1\Admin\Verification\RejectVerificationController;
use App\Http\Controllers\Api\V1\Admin\Verification\RevokeVerificationController;
use App\Http\Controllers\Api\V1\Admin\Verification\ShowVerificationController;
use App\Http\Controllers\Api\V1\Auth\CheckAvailabilityController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResendCodeController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Bid\AcceptBidController;
use App\Http\Controllers\Api\V1\Bid\ListMyBidsController;
use App\Http\Controllers\Api\V1\Bid\ListTaskBidsController;
use App\Http\Controllers\Api\V1\Bid\PlaceBidController;
use App\Http\Controllers\Api\V1\Bid\WithdrawBidController;
use App\Http\Controllers\Api\V1\Category\ListCategoriesController;
use App\Http\Controllers\Api\V1\Payment\HoldPaymentController;
use App\Http\Controllers\Api\V1\Payment\ShowTaskPaymentController;
use App\Http\Controllers\Api\V1\Review\CreateReviewController;
use App\Http\Controllers\Api\V1\Review\ListUserReviewsController;
use App\Http\Controllers\Api\V1\Skill\ListSkillsController;
use App\Http\Controllers\Api\V1\Task\CancelTaskController;
use App\Http\Controllers\Api\V1\Task\CreateTaskController;
use App\Http\Controllers\Api\V1\Task\ListMyPostedTasksController;
use App\Http\Controllers\Api\V1\Task\ListMyWorkedTasksController;
use App\Http\Controllers\Api\V1\Task\ListOpenTasksController;
use App\Http\Controllers\Api\V1\Task\PublishTaskController;
use App\Http\Controllers\Api\V1\Task\ShowTaskController;
use App\Http\Controllers\Api\V1\Task\StartTaskController;
use App\Http\Controllers\Api\V1\User\ListVerificationsController;
use App\Http\Controllers\Api\V1\User\ListWorkersController;
use App\Http\Controllers\Api\V1\User\ShowMeController;
use App\Http\Controllers\Api\V1\User\ShowWorkerProfileController;
use App\Http\Controllers\Api\V1\User\SubmitVerificationController;
use App\Http\Controllers\Api\V1\User\UpdateProfileController;
use App\Http\Controllers\Api\V1\User\UpsertWorkerProfileController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Satu baris per invokable controller. Selalu berversi, selalu bernama.
|
| Tiga lapis pengamanan dideklarasikan DI SINI agar seluruh aturannya terbaca
| dalam satu berkas, dan controller tetap tiga pernyataan:
|
|  1. auth:sanctum          — token harus sah
|  2. abilities:token:access — token harus JENIS access, bukan long_lived.
|                              Tanpa lapis ini long_lived token bisa memanggil
|                              seluruh API, dan umurnya panjang.
|  3. throttle:<pembatas>   — batas laju; definisinya di RateLimitServiceProvider
|
| Aturan otorisasi per-objek memakai ->can(), policy-nya di app/Policies.
|------------------------------------------------------------------------------
*/

Route::prefix('v1')->name('v1.')->group(function (): void {

    // ── Auth: satu-satunya kelompok yang boleh diakses tanpa token ──────────
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', RegisterController::class)
            ->middleware('throttle:register')->name('register');

        Route::post('check-availability', CheckAvailabilityController::class)
            ->middleware('throttle:availability')->name('check-availability');

        Route::post('verify-email', VerifyEmailController::class)
            ->middleware('throttle:verify')->name('verify-email');

        Route::post('resend-code', ResendCodeController::class)
            ->middleware('throttle:resend')->name('resend-code');

        Route::post('forgot-password', ForgotPasswordController::class)
            ->middleware('throttle:forgot')->name('forgot-password');

        Route::post('login', LoginController::class)
            ->middleware('throttle:login')->name('login');

        // HANYA long_lived token yang boleh menukar diri jadi access baru.
        Route::post('refresh', RefreshTokenController::class)
            ->middleware([
                'auth:sanctum',
                'abilities:'.TokenAbility::Refresh->value,
                'throttle:refresh',
            ])->name('refresh');

        // Logout menerima kedua jenis token: pengguna harus tetap bisa keluar
        // walau access token-nya sudah kedaluwarsa.
        Route::post('logout', LogoutController::class)
            ->middleware(['auth:sanctum', 'throttle:api'])->name('logout');
    });

    // ── Seluruh API aplikasi: wajib access token ────────────────────────────
    Route::middleware([
        'auth:sanctum',
        'abilities:'.TokenAbility::Access->value,
        'throttle:api',
    ])->group(function (): void {

        // Data acuan
        Route::get('categories', ListCategoriesController::class)->name('categories.index');
        Route::get('skills', ListSkillsController::class)->name('skills.index');

        // Akun
        Route::get('me', ShowMeController::class)->name('me.show');
        Route::patch('me', UpdateProfileController::class)->name('me.update');
        // Profil PEKERJA — tabel sendiri, sisi lain dari akun yang sama.
        // Identitas (nama, jenis kelamin, tanggal lahir) tetap diubah lewat
        // PATCH /me; yang di sini hanya yang khas pekerja.
        //
        // PUT, bukan POST: klien tidak perlu tahu apakah profilnya sudah
        // pernah dibuat, dan mengirim isi yang sama dua kali menghasilkan
        // keadaan yang sama.
        Route::get('me/worker', ShowWorkerProfileController::class)->name('me.worker.show');
        Route::put('me/worker', UpsertWorkerProfileController::class)->name('me.worker.update');

        Route::get('me/verifications', ListVerificationsController::class)->name('me.verifications.index');
        Route::post('me/verifications', SubmitVerificationController::class)->name('me.verifications.store');

        // Pekerja yang siap menerima pekerjaan — sisi sebaliknya dari feed
        // task. Cursor pagination, berangkat dari `user_workers` supaya
        // urutannya "yang baru siap bekerja", bukan "yang baru mendaftar".
        Route::get('workers', ListWorkersController::class)->name('workers.index');

        // Task
        Route::get('tasks', ListOpenTasksController::class)->name('tasks.index');
        Route::get('tasks/posted', ListMyPostedTasksController::class)->name('tasks.posted');
        Route::get('tasks/worked', ListMyWorkedTasksController::class)->name('tasks.worked');
        Route::post('tasks', CreateTaskController::class)
            ->middleware('throttle:write')->name('tasks.store');
        Route::get('tasks/{task}', ShowTaskController::class)
            ->can('view', 'task')->name('tasks.show');
        Route::post('tasks/{task}/publish', PublishTaskController::class)
            ->can('update', 'task')->name('tasks.publish');
        Route::post('tasks/{task}/cancel', CancelTaskController::class)
            ->can('cancel', 'task')->name('tasks.cancel');
        // Berhenti merekrut lebih awal: target dikunci di jumlah yang sudah
        // diterima, lelang ditutup, task masuk deal.
        Route::post('tasks/{task}/start', StartTaskController::class)
            ->can('update', 'task')->name('tasks.start');

        // Lelang
        Route::post('tasks/{task}/bids', PlaceBidController::class)
            ->middleware('throttle:write')->name('tasks.bids.store');
        Route::get('tasks/{task}/bids', ListTaskBidsController::class)
            ->can('manageBids', 'task')->name('tasks.bids.index');
        Route::get('bids/mine', ListMyBidsController::class)->name('bids.mine');
        Route::post('bids/{bid}/withdraw', WithdrawBidController::class)
            ->can('withdraw', 'bid')->name('bids.withdraw');
        // DEAL
        Route::post('bids/{bid}/accept', AcceptBidController::class)
            ->can('accept', 'bid')->name('bids.accept');

        // Uang. Pemberi kerja MELAPOR sudah transfer; yang menahan dana
        // (dan dengan itu membuka activity) hanya pengelola, lewat
        // POST /admin/payments/{payment}/confirm.
        Route::get('tasks/{task}/payment', ShowTaskPaymentController::class)
            ->can('view', 'task')->name('tasks.payment.show');
        // Path dan nama rutenya TETAP `payment/hold` demi klien yang sudah ada,
        // tapi panggilan ini tidak lagi menahan dana — ia hanya melapor.
        Route::post('tasks/{task}/payment/hold', HoldPaymentController::class)
            ->can('pay', 'task')->name('tasks.payment.hold');

        // Activity
        Route::get('activities/mine', ListMyActivitiesController::class)->name('activities.mine');
        Route::get('activities/{activity}', ShowActivityController::class)
            ->can('view', 'activity')->name('activities.show');
        Route::post('activities/{activity}/start', StartActivityController::class)
            ->can('work', 'activity')->name('activities.start');
        Route::post('activities/{activity}/submit', SubmitActivityController::class)
            ->can('work', 'activity')->name('activities.submit');
        Route::post('activities/{activity}/approve', ApproveActivityController::class)
            ->can('judge', 'activity')->name('activities.approve');
        Route::post('activities/{activity}/reject', RejectActivityController::class)
            ->can('judge', 'activity')->name('activities.reject');

        // Penilaian dua arah
        Route::post('tasks/{task}/reviews', CreateReviewController::class)
            ->can('review', 'task')->name('tasks.reviews.store');
        Route::get('users/{user}/reviews', ListUserReviewsController::class)->name('users.reviews.index');
    });
    /*
    |--------------------------------------------------------------------------
    | PENGELOLA — populasi token yang BERBEDA
    |--------------------------------------------------------------------------
    |
    | Guard `admin`, bukan `sanctum`. Bedanya bukan kosmetik: provider kedua
    | guard disebut eksplisit di config/auth.php, dan itulah yang membuat token
    | pengguna ditolak di sini dan token pengelola ditolak di endpoint
    | pengguna. Tanpa provider eksplisit, Sanctum meloloskan pemilik token
    | jenis apa pun — penjelasan lengkapnya di config/auth.php.
    |
    | Empat lapis, satu lebih banyak daripada endpoint pengguna:
    |
    |  1. auth:admin                    — token sah DAN milik App\Models\Admin
    |  2. abilities:admin:access        — token jenis access, bukan long_lived
    |  3. admin.active                  — akunnya belum dinonaktifkan. Lapis
    |                                     ini ada karena status akun tidak
    |                                     tersimpan di dalam token, dan token
    |                                     itu hidup delapan jam.
    |  4. throttle:admin                — batas laju
    |
    | Kelompok `admins` di dalamnya menambah lapis kelima: ->can(), yang
    | menuntut peran super_admin. Aturannya di App\Policies\AdminPolicy,
    | sumbernya AdminRole::can().
    |
    */

    Route::prefix('admin')->name('admin.')->group(function (): void {

        // ── Auth pengelola: tidak ada pendaftaran, dan itu disengaja ───────
        // Akun pengelola hanya lahir dari dua tempat: perintah
        // `php artisan sekarya:admin create` (super_admin, sekali) dan
        // POST /admin/admins (dipanggil super_admin). Sebuah endpoint
        // pendaftaran pengelola adalah pintu kenaikan hak akses yang terbuka
        // ke internet.
        Route::post('auth/login', AdminLoginController::class)
            ->middleware('throttle:admin_login')->name('auth.login');

        // HANYA long_lived pengelola yang boleh menukar diri jadi access baru.
        Route::post('auth/refresh', AdminRefreshTokenController::class)
            ->middleware([
                'auth:admin',
                'abilities:'.TokenAbility::AdminRefresh->value,
                'throttle:refresh',
            ])->name('auth.refresh');

        // Logout menerima kedua jenis token dan TIDAK memakai `admin.active`:
        // pengelola yang baru dinonaktifkan harus tetap bisa mencabut
        // tokennya sendiri.
        Route::post('auth/logout', AdminLogoutController::class)
            ->middleware(['auth:admin', 'throttle:admin'])->name('auth.logout');

        Route::middleware([
            'auth:admin',
            'abilities:'.TokenAbility::AdminAccess->value,
            'admin.active',
            'throttle:admin',
        ])->group(function (): void {

            Route::get('me', ShowAdminMeController::class)->name('me.show');

            // ── Verifikasi identitas & rekening ────────────────────────────
            Route::get('verifications', ListVerificationQueueController::class)
                ->name('verifications.index');
            // Detail MENULIS jejak baca — di sinilah NIK keluar terbaca.
            Route::get('verifications/{verification}', ShowVerificationController::class)
                ->name('verifications.show');
            Route::post('verifications/{verification}/approve', ApproveVerificationController::class)
                ->name('verifications.approve');
            Route::post('verifications/{verification}/reject', RejectVerificationController::class)
                ->name('verifications.reject');
            Route::post('verifications/{verification}/revoke', RevokeVerificationController::class)
                ->name('verifications.revoke');

            // ── Konfirmasi transfer ───────────────────────────────────────
            Route::get('payments', ListPaymentQueueController::class)->name('payments.index');
            Route::get('payments/{payment}', ShowPaymentController::class)->name('payments.show');
            // Satu-satunya jalan ke `held`, dan `held` membuka pekerjaan.
            Route::post('payments/{payment}/confirm', ConfirmPaymentController::class)
                ->name('payments.confirm');
            Route::post('payments/{payment}/reject', RejectPaymentController::class)
                ->name('payments.reject');

            // ── Moderasi pengguna ─────────────────────────────────────────
            Route::get('users', ListUsersController::class)->name('users.index');
            Route::get('users/{user}', ShowUserController::class)->name('users.show');
            Route::post('users/{user}/suspend', SuspendUserController::class)->name('users.suspend');
            Route::post('users/{user}/ban', BanUserController::class)->name('users.ban');
            Route::post('users/{user}/reinstate', ReinstateUserController::class)->name('users.reinstate');

            // ── Akun pengelola — HANYA super_admin ────────────────────────
            //
            // Gerbangnya middleware, bukan Policy: aturannya kasar (soal peran
            // pemanggil, bukan soal objeknya), dan penolakan lewat Policy
            // keluar sebagai galat bawaan Laravel tanpa kode mesin —
            // sedangkan Action menolak hal yang sama dengan
            // `admin_access_denied`. Satu kegagalan, satu bentuk respons.
            //
            // Sasaran super_admin ditolak di dalam Action, dengan kode
            // `super_admin_protected`. Penjaga terakhirnya hook `deleting` di
            // model Admin, dan jumlahnya dijaga indeks unique di basis data.
            Route::middleware('admin.manages-admins')->group(function (): void {
                Route::get('admins', ListAdminsController::class)->name('admins.index');
                Route::post('admins', CreateAdminController::class)->name('admins.store');
                Route::get('admins/{admin}', ShowAdminController::class)->name('admins.show');
                Route::delete('admins/{admin}', DeleteAdminController::class)->name('admins.destroy');
            });
        });
    });
});
