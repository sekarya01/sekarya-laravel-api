<?php

declare(strict_types=1);

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\Activity\ApproveActivityController;
use App\Http\Controllers\Api\V1\Activity\ConfirmArrivalController;
use App\Http\Controllers\Api\V1\Activity\DepartActivityController;
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
use App\Http\Controllers\Api\V1\Admin\Wallet\CompleteWithdrawalController;
use App\Http\Controllers\Api\V1\Admin\Wallet\ConfirmTopupController;
use App\Http\Controllers\Api\V1\Admin\Wallet\ListTopupQueueController;
use App\Http\Controllers\Api\V1\Admin\Wallet\ListWithdrawalQueueController;
use App\Http\Controllers\Api\V1\Admin\Wallet\RejectTopupController;
use App\Http\Controllers\Api\V1\Admin\Wallet\RejectWithdrawalController;
use App\Http\Controllers\Api\V1\Admin\WorkerInvite\CreateWorkerInviteCodeController;
use App\Http\Controllers\Api\V1\Admin\WorkerInvite\DeactivateWorkerInviteCodeController;
use App\Http\Controllers\Api\V1\Admin\WorkerInvite\ListWorkerInviteCodesController;
use App\Http\Controllers\Api\V1\Admin\WorkerInvite\ListWorkerInviteRedemptionsController;
use App\Http\Controllers\Api\V1\Admin\WorkerInvite\ShowWorkerInviteCodeController;
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
use App\Http\Controllers\Api\V1\Task\ApproveTaskCancelController;
use App\Http\Controllers\Api\V1\Task\CancelTaskController;
use App\Http\Controllers\Api\V1\Task\CreateTaskController;
use App\Http\Controllers\Api\V1\Task\ListMyPostedTasksController;
use App\Http\Controllers\Api\V1\Task\ListMyWorkedTasksController;
use App\Http\Controllers\Api\V1\Task\ListOpenTasksController;
use App\Http\Controllers\Api\V1\Task\PublishTaskController;
use App\Http\Controllers\Api\V1\Task\RejectTaskCancelController;
use App\Http\Controllers\Api\V1\Task\RequestTaskCancelController;
use App\Http\Controllers\Api\V1\Task\ShowTaskCancelRequestController;
use App\Http\Controllers\Api\V1\Task\ShowTaskController;
use App\Http\Controllers\Api\V1\Task\StartTaskController;
use App\Http\Controllers\Api\V1\Task\UpdateTaskController;
use App\Http\Controllers\Api\V1\Task\WithdrawTaskCancelController;
use App\Http\Controllers\Api\V1\Upload\StoreUploadController;
use App\Http\Controllers\Api\V1\User\CheckWorkerInviteAvailabilityController;
use App\Http\Controllers\Api\V1\User\ForgetDeviceController;
use App\Http\Controllers\Api\V1\User\ListVerificationsController;
use App\Http\Controllers\Api\V1\User\ListWorkersController;
use App\Http\Controllers\Api\V1\User\RedeemWorkerInviteCodeController;
use App\Http\Controllers\Api\V1\User\RegisterDeviceController;
use App\Http\Controllers\Api\V1\User\ShowMeController;
use App\Http\Controllers\Api\V1\User\ShowWorkerProfileController;
use App\Http\Controllers\Api\V1\User\SubmitVerificationController;
use App\Http\Controllers\Api\V1\User\UpdateProfileController;
use App\Http\Controllers\Api\V1\User\UpsertWorkerProfileController;
use App\Http\Controllers\Api\V1\Wallet\CancelTopupController;
use App\Http\Controllers\Api\V1\Wallet\CancelWithdrawalController;
use App\Http\Controllers\Api\V1\Wallet\CreateTopupController;
use App\Http\Controllers\Api\V1\Wallet\CreateWithdrawalController;
use App\Http\Controllers\Api\V1\Wallet\ListTopupsController;
use App\Http\Controllers\Api\V1\Wallet\ListWalletEntriesController;
use App\Http\Controllers\Api\V1\Wallet\ListWithdrawalsController;
use App\Http\Controllers\Api\V1\Wallet\ShowWalletController;
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
        // Pendaftaran mitra pakai kode undangan 8 char (hash sha256 di DB).
        // POST: menukar kode sekali pakai/kuota-terbatas menjadi baris
        // `user_workers` + `active_mode = working`.
        Route::post('me/worker/redeem', RedeemWorkerInviteCodeController::class)
            ->middleware('throttle:write')->name('me.worker.redeem');
        // Sinyal ketersediaan (boolean saja, tanpa isi kode): ada kode yang
        // hidup di kota/provinsi ini? "Saat ini" = jam server, bukan jam
        // perangkat. Didaftarkan SEBELUM `me/worker/redeem` bukan masalah —
        // path-nya beda, tidak tertangkap sebagai `{code}`.
        Route::get('me/worker/invite-availability', CheckWorkerInviteAvailabilityController::class)
            ->name('me.worker.invite-availability');

        Route::get('me/verifications', ListVerificationsController::class)->name('me.verifications.index');
        Route::post('me/verifications', SubmitVerificationController::class)->name('me.verifications.store');

        // Perangkat untuk push notification. Klien mendaftarkan tokennya tiap
        // aplikasi dibuka (bukan hanya sekali saat login) supaya perpindahan
        // akun di satu ponsel ikut tersegarkan, lalu melepasnya saat logout.
        Route::post('me/devices', RegisterDeviceController::class)
            ->middleware('throttle:write')->name('me.devices.store');
        Route::delete('me/devices/{token}', ForgetDeviceController::class)
            ->name('me.devices.destroy');

        // ── Saldo ──────────────────────────────────────────────────────────
        //
        // Uangnya masuk dari tiga arah — isi ulang, pengembalian dana task
        // yang batal, dan upah pekerja saat dana dilepas — dan keluar lewat
        // satu pintu: penarikan ke rekening yang sudah diverifikasi.
        //
        // Dua hal yang tidak boleh dibaca terbalik:
        //
        //  1. `POST me/wallet/topups` TIDAK menambah saldo. Ia melapor sudah
        //     transfer, persis seperti `tasks/{task}/payment/hold`. Yang
        //     menambah saldo hanya POST /admin/wallet/topups/{topup}/confirm.
        //  2. `POST me/wallet/withdrawals` LANGSUNG mengurangi saldo. Kalau
        //     pemotongan menunggu pencairan, saldo yang sama bisa diminta
        //     berkali-kali selama antrean pengelola belum tersentuh.
        Route::get('me/wallet', ShowWalletController::class)->name('me.wallet.show');
        Route::get('me/wallet/entries', ListWalletEntriesController::class)->name('me.wallet.entries.index');

        Route::get('me/wallet/topups', ListTopupsController::class)->name('me.wallet.topups.index');
        Route::post('me/wallet/topups', CreateTopupController::class)
            ->middleware('throttle:write')->name('me.wallet.topups.store');
        Route::post('me/wallet/topups/{topup}/cancel', CancelTopupController::class)
            ->can('cancel', 'topup')->name('me.wallet.topups.cancel');

        Route::get('me/wallet/withdrawals', ListWithdrawalsController::class)->name('me.wallet.withdrawals.index');
        Route::post('me/wallet/withdrawals', CreateWithdrawalController::class)
            ->middleware('throttle:write')->name('me.wallet.withdrawals.store');
        Route::post('me/wallet/withdrawals/{withdrawal}/cancel', CancelWithdrawalController::class)
            ->can('cancel', 'withdrawal')->name('me.wallet.withdrawals.cancel');

        // Pekerja yang siap menerima pekerjaan — sisi sebaliknya dari feed
        // task. Cursor pagination, berangkat dari `user_workers` supaya
        // urutannya "yang baru siap bekerja", bukan "yang baru mendaftar".
        Route::get('workers', ListWorkersController::class)->name('workers.index');

        // Unggah gambar (foto task, avatar). Satu berkas per panggilan —
        // mobile mengunggah tiap foto lalu memakai `path` yang dikembalikan
        // di `photos[]` saat buat task.
        Route::post('uploads', StoreUploadController::class)
            ->middleware('throttle:write')->name('uploads.store');

        // Task
        Route::get('tasks', ListOpenTasksController::class)->name('tasks.index');
        Route::get('tasks/posted', ListMyPostedTasksController::class)->name('tasks.posted');
        Route::get('tasks/worked', ListMyWorkedTasksController::class)->name('tasks.worked');
        Route::post('tasks', CreateTaskController::class)
            ->middleware('throttle:write')->name('tasks.store');
        Route::get('tasks/{task}', ShowTaskController::class)
            ->can('view', 'task')->name('tasks.show');
        // Sunting isi task. Parsial: ruas yang tidak dikirim tidak disentuh.
        // Hanya selama `draft`/`open` — Action yang menjaganya, karena itu
        // aturan bisnis, bukan soal siapa pemiliknya.
        Route::put('tasks/{task}', UpdateTaskController::class)
            ->middleware('throttle:write')
            ->can('update', 'task')->name('tasks.update');
        Route::post('tasks/{task}/publish', PublishTaskController::class)
            ->can('update', 'task')->name('tasks.publish');
        Route::post('tasks/{task}/cancel', CancelTaskController::class)
            ->can('cancel', 'task')->name('tasks.cancel');
        // Pembatalan ber-PERSETUJUAN: dipakai bila sudah ada pekerja yang
        // deal — `POST cancel` langsung hanya untuk task yang belum deal.
        // Poster meminta, pekerja menyetujui/menolak dari popup di Detail
        // Kerjaan (dibaca lewat `cancel_request` di `GET tasks/{task}`).
        Route::post('tasks/{task}/cancel-requests', RequestTaskCancelController::class)
            ->middleware('throttle:write')
            ->can('requestCancel', 'task')->name('tasks.cancel-requests.store');
        Route::get('tasks/{task}/cancel-request', ShowTaskCancelRequestController::class)
            ->can('view', 'task')->name('tasks.cancel-request.show');
        Route::post('tasks/{task}/cancel-requests/{cancelRequest}/approve', ApproveTaskCancelController::class)
            ->scopeBindings()->can('approve', 'cancelRequest')->name('tasks.cancel-requests.approve');
        Route::post('tasks/{task}/cancel-requests/{cancelRequest}/reject', RejectTaskCancelController::class)
            ->scopeBindings()->can('reject', 'cancelRequest')->name('tasks.cancel-requests.reject');
        Route::post('tasks/{task}/cancel-requests/{cancelRequest}/withdraw', WithdrawTaskCancelController::class)
            ->scopeBindings()->can('withdraw', 'cancelRequest')->name('tasks.cancel-requests.withdraw');
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
        // Perjalanan: pekerja mengumumkan berangkat, PEMBERI KERJA yang
        // mengakui kedatangannya. Yang melihat orangnya sampai adalah tuan
        // rumah — kalau yang datang boleh menyatakannya sendiri, pengakuan itu
        // tidak berarti apa pun.
        Route::post('activities/{activity}/depart', DepartActivityController::class)
            ->can('work', 'activity')->name('activities.depart');
        Route::post('activities/{activity}/arrived', ConfirmArrivalController::class)
            ->can('judge', 'activity')->name('activities.arrived');
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

            // ── Saldo: isi ulang & pencairan ──────────────────────────────
            //
            // Dua antrean manual, dan keduanya menyentuh uang sungguhan.
            // `topups/{topup}/confirm` adalah SATU-SATUNYA jalan saldo bisa
            // bertambah dari isi ulang — sama seperti `payments/{payment}/
            // confirm` adalah satu-satunya jalan menuju `held`.
            //
            // Nomor rekening tujuan pencairan TIDAK keluar di antrean ini.
            // Ia terbaca di GET /admin/verifications/{verification}, dan
            // pembacaan di sana dicatat sebagai `verification.viewed`.
            Route::get('wallet/topups', ListTopupQueueController::class)->name('wallet.topups.index');
            Route::post('wallet/topups/{topup}/confirm', ConfirmTopupController::class)
                ->name('wallet.topups.confirm');
            Route::post('wallet/topups/{topup}/reject', RejectTopupController::class)
                ->name('wallet.topups.reject');

            Route::get('wallet/withdrawals', ListWithdrawalQueueController::class)->name('wallet.withdrawals.index');
            // Menandai transfer sudah dikirim. TIDAK memotong saldo lagi —
            // saldonya sudah ditahan sejak penarikan diminta.
            Route::post('wallet/withdrawals/{withdrawal}/complete', CompleteWithdrawalController::class)
                ->name('wallet.withdrawals.complete');
            // Menolak MENGEMBALIKAN tahanan itu.
            Route::post('wallet/withdrawals/{withdrawal}/reject', RejectWithdrawalController::class)
                ->name('wallet.withdrawals.reject');

            // ── Kode undangan mitra ───────────────────────────────────────
            //
            // Plain 8 char tampil terus di sini (kolom `code`) supaya bisa
            // dibagikan kapan saja — keputusan produk, lihat migrasi
            // `2026_09_21_000002`. Yang tidak pernah keluar adalah hash.
            Route::get('worker-invite-codes', ListWorkerInviteCodesController::class)
                ->name('worker-invites.index');
            Route::post('worker-invite-codes', CreateWorkerInviteCodeController::class)
                ->name('worker-invites.store');
            Route::get('worker-invite-codes/{code}', ShowWorkerInviteCodeController::class)
                ->name('worker-invites.show');
            // Pintu darurat kalau kode bocor: redeem berhenti, jejak yang
            // sudah terjadi tetap ada.
            Route::post('worker-invite-codes/{code}/deactivate', DeactivateWorkerInviteCodeController::class)
                ->name('worker-invites.deactivate');
            // Siapa saja yang memakai kode ini — dasar meja verifikasi.
            Route::get('worker-invite-codes/{code}/redemptions', ListWorkerInviteRedemptionsController::class)
                ->name('worker-invites.redemptions.index');

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
