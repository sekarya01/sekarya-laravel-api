<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Domain\AdminAccessDeniedException;
use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lapis ketiga gerbang `/admin`, setelah guard dan ability.
 *
 * Yang dijaganya tidak dijaga dua lapis sebelumnya: token yang SAH milik
 * pengelola yang sudah dinonaktifkan. Guard hanya memeriksa keaslian token,
 * ability hanya memeriksa jenisnya — status akun tidak ikut tersimpan di
 * dalam token, dan tokennya hidup delapan jam.
 */
final class EnsureActiveAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            throw AdminAccessDeniedException::becauseNotAnAdmin();
        }

        if (! $admin->status->isActive()) {
            throw AdminAccessDeniedException::becauseSuspended();
        }

        return $next($request);
    }
}
