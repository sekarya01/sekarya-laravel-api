<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\UserWorker;
use Illuminate\Http\Request;

/**
 * Profil pekerja MILIK SENDIRI — bukan yang dilihat pemberi kerja.
 *
 * Yang dilihat orang lain tetap `PublicUserResource`, dan ia lebih tertutup:
 * alamat jalan dan koordinat tidak pernah keluar dari sana.
 *
 * Bentuk responsnya sengaja punya DUA lapis, dan itu yang membuat formulir di
 * sisi klien bisa benar:
 *
 * - Di tingkat atas: nilai TERPAKAI — sudah diresolusi, jadi klien tidak perlu
 *   tahu aturan "NULL berarti pakai punya akun" untuk bisa menampilkannya.
 * - Di `own`: nilai yang benar-benar DIISI SENDIRI, apa adanya, `null` kalau
 *   diwarisi dari akun.
 *
 * Tanpa lapis kedua, formulir sunting tidak punya cara membedakan "nama
 * pekerja saya memang Budi" dari "saya belum mengisinya, itu nama akun saya" —
 * dan begitu formulirnya disimpan kembali, seluruh nilai warisan berubah
 * menjadi nilai yang diisi sendiri. Ikatannya ke akun putus diam-diam, dan
 * ganti nama di profil akun berhenti terlihat di sini.
 *
 * @mixin UserWorker
 */
final class WorkerProfileResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $address = $this->resource->resolvedAddress();

        return [
            // Profilnya belum tentu pernah dibuat. GET tidak membuat baris —
            // yang dikembalikan instance sementara berisi warisan dari akun.
            'configured' => $this->resource->exists,

            // ── Nilai terpakai ──────────────────────────────────────────────
            'name' => $this->resource->resolvedName(),
            'contact_phone' => $this->resource->resolvedPhone(),
            'avatar_url' => $this->publicUrl($this->resource->resolvedAvatarPath()),
            // Identitas: selalu dari akun, tidak bisa berbeda di sini.
            // Ubahnya lewat PATCH /me.
            // Dijamin terisi untuk profil yang dibuat sejak identitas
            // diwajibkan. Baris lama bisa masih null — dan baris itu tidak
            // akan pernah `ready_to_work`.
            'gender' => $this->resource->gender()?->value,
            'age' => $this->resource->age(),
            'address' => $address,
            'work_location' => [
                'latitude' => $this->latitude === null ? null : (float) $this->latitude,
                'longitude' => $this->longitude === null ? null : (float) $this->longitude,
                'radius_km' => $this->radius_km,
            ],

            // ── Nilai yang diisi sendiri; null = warisan dari akun ──────────
            'own' => [
                'display_name' => $this->display_name,
                'contact_phone' => $this->contact_phone,
                'avatar_path' => $this->avatar_path,
                'address_line' => $this->address_line,
                'city' => $this->city,
                'province' => $this->province,
                'postal_code' => $this->postal_code,
            ],

            // Siap menerima pekerjaan: barisnya ada DAN identitasnya sudah
            // diverifikasi pengelola. `configured` di atas menjawab
            // pertanyaan yang berbeda — profilnya sudah pernah diisi — dan
            // pemiliknya perlu melihat keduanya untuk tahu ia sedang menunggu
            // apa.
            'ready_to_work' => $this->resource->user?->readyToWork() ?? false,

            // ── Reputasi. Hanya Action yang menulisnya. ─────────────────────
            'as_worker' => [
                'rating_avg' => (float) $this->worker_rating_avg,
                'rating_count' => $this->worker_rating_count,
                'tasks_completed' => $this->tasks_completed,
                'bids_won' => $this->bids_won,
            ],

            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
