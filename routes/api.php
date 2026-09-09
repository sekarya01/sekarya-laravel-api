<?php

declare(strict_types=1);

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\Activity\ApproveActivityController;
use App\Http\Controllers\Api\V1\Activity\ListMyActivitiesController;
use App\Http\Controllers\Api\V1\Activity\RejectActivityController;
use App\Http\Controllers\Api\V1\Activity\ShowActivityController;
use App\Http\Controllers\Api\V1\Activity\StartActivityController;
use App\Http\Controllers\Api\V1\Activity\SubmitActivityController;
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
use App\Http\Controllers\Api\V1\User\ShowMeController;
use App\Http\Controllers\Api\V1\User\SubmitVerificationController;
use App\Http\Controllers\Api\V1\User\UpdateProfileController;
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

        Route::post('verify-email', VerifyEmailController::class)
            ->middleware('throttle:verify')->name('verify-email');

        Route::post('resend-code', ResendCodeController::class)
            ->middleware('throttle:resend')->name('resend-code');

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
        Route::get('me/verifications', ListVerificationsController::class)->name('me.verifications.index');
        Route::post('me/verifications', SubmitVerificationController::class)->name('me.verifications.store');

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

        // Uang (STUB — nanti dipicu webhook gateway, bukan endpoint ini)
        Route::get('tasks/{task}/payment', ShowTaskPaymentController::class)
            ->can('view', 'task')->name('tasks.payment.show');
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
});
