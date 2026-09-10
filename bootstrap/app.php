<?php

declare(strict_types=1);

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\AxiomRequestLogger;
use App\Http\Middleware\EnsureActiveAdmin;
use App\Http\Middleware\EnsureAdminManagesAdmins;
use App\Http\Middleware\EnsureSuperAdminWeb;
use App\Logging\Axiom\ExceptionRecorder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS: middleware HandleCors sudah ada di tumpukan global Laravel;
        // aturannya di config/cors.php.

        // Alias ability Sanctum — TIDAK terdaftar otomatis di Laravel 11+.
        // Tanpa ini, `abilities:...` di routes/api.php akan gagal senyap
        // sebagai middleware yang tak dikenal dan pembatasan token hilang.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,

            // Gerbang `/admin` lapis ketiga: status akun pengelola diperiksa
            // ulang di setiap permintaan. Guard memeriksa keaslian token,
            // ability memeriksa jenisnya — keduanya tidak tahu akunnya sudah
            // dinonaktifkan atau belum, dan token itu hidup delapan jam.
            'admin.active' => EnsureActiveAdmin::class,

            // Gerbang kelompok `/admin/admins`: hanya super_admin. Aturannya
            // dibaca dari AdminRole::canManageAdmins(), bukan ditulis ulang.
            'admin.manages-admins' => EnsureAdminManagesAdmins::class,

            // Gerbang dasbor web /access/super_admin: sesi + super_admin.
            'super_admin.web' => EnsureSuperAdminWeb::class,
        ]);

        // Observability (Axiom) dipasang PALING LUAR pada grup api, sebelum
        // throttle dan autentikasi.
        //
        // Urutannya adalah keputusan, bukan kebetulan. Dipasang di dalam,
        // request yang ditolak `throttle` (429) atau `auth:sanctum` (401)
        // tidak akan pernah sampai ke pencatat — padahal justru itu event
        // yang paling dibutuhkan saat menyelidiki abuse. Di luar, durasi yang
        // tercatat juga durasi yang benar-benar dirasakan klien.
        //
        // Kelasnya sendiri tidak akan mengirim apa pun selama
        // `AXIOM_ENABLED=false`, jadi aman terpasang di semua lingkungan.
        $middleware->prependToGroup('api', AxiomRequestLogger::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Setiap exception yang dilaporkan Laravel diteruskan ke Axiom.
         *
         * `report()` MENAMBAH, tidak menggantikan: penanganan bawaan tetap
         * berjalan sehingga laravel.log lokal tidak kehilangan apa pun. Kalau
         * Axiom sedang tidak bisa dihubungi, jejak galatnya masih ada di server.
         *
         * ExceptionRecorder diambil dari container saat dipakai, bukan lewat
         * penyuntikan di sini — closure ini dievaluasi sangat awal, sebelum
         * seluruh service provider selesai boot.
         *
         * Yang TIDAK sampai ke sini: ValidationException dan
         * AuthenticationException, karena keduanya ada di daftar "tidak
         * dilaporkan" milik Laravel. Keduanya tetap tercatat — sebagai
         * `security.validation_failed` dan `security.unauthenticated` dari
         * middleware, yang justru bentuk yang lebih berguna.
         */
        $exceptions->report(function (Throwable $e): void {
            app(ExceptionRecorder::class)->record($e);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Satu-satunya tempat pelanggaran aturan bisnis menjadi respons HTTP.
        // Action melempar; Action tidak pernah tahu soal status code.
        $exceptions->render(fn (DomainException $e) => new JsonResponse([
            'message' => $e->getMessage(),
            'code' => $e->errorCode(),
            ...($e->context() === [] ? [] : ['context' => $e->context()]),
        ], $e->httpStatus()));

        // Token tidak ada / kedaluwarsa / dicabut. Diberi kode mesin sendiri
        // supaya klien bisa membedakan "perlu refresh" dari galat lain dan
        // langsung memanggil /auth/refresh tanpa menebak dari pesan.
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? new JsonResponse([
                'message' => 'Token tidak valid atau sudah kedaluwarsa.',
                'code' => 'unauthenticated',
            ], Response::HTTP_UNAUTHORIZED)
            : null);
    })->create();
