<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Verification;

use App\Actions\Admin\Verification\ShowVerificationDocumentAction;
use App\Models\UserVerification;
use App\Support\VerificationDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan berkas KTP/selfie ke pengelola (G2b). Berkas dibaca dari disk
 * privat dan dialirkan lewat respons ber-auth — tidak ada URL publik, dan
 * setiap pembacaan tercatat (lihat ShowVerificationDocumentAction).
 */
final class ShowVerificationDocumentController
{
    public function __construct(private readonly ShowVerificationDocumentAction $action) {}

    public function __invoke(Request $request, UserVerification $verification, string $kind): StreamedResponse
    {
        $path = $this->action->handle($verification, $request->user(), $kind, $request->ip());

        abort_if($path === null, 404);

        return Storage::disk(VerificationDocuments::DISK)->response($path);
    }
}
