<?php

declare(strict_types=1);

namespace App\Actions\Upload;

use App\Models\User;
use App\Support\ProofPhotos;
use Illuminate\Http\UploadedFile;

/**
 * Simpan satu unggahan publik dan kembalikan `path`-nya.
 *
 * Nama berkas selalu acak (hashName): nama asli dari perangkat tidak pernah
 * menyentuh disk — "KTP_Budi.jpg" tidak boleh bocor lewat URL. Foto bukti
 * kerja menambah tanda pemilik di depannya, supaya penyerahan hasil bisa
 * menolak foto milik orang lain (ProofPhotos). Dokumen identitas TIDAK lewat
 * sini (penyimpanan privat, VerificationDocuments).
 */
final class StoreUploadAction
{
    public function __construct(private readonly ProofPhotos $proofs) {}

    /** [#impure] [#io: disk] */
    public function handle(UploadedFile $file, string $purpose, User $user): string
    {
        return match ($purpose) {
            'avatar' => $file->store('uploads/avatars', 'public'),
            'proof' => $this->proofs->store($file, $user),
            'review' => $file->store('uploads/reviews', 'public'),
            'update' => $file->store('uploads/updates', 'public'),
            default => $file->store('uploads/tasks', 'public'),
        };
    }
}
