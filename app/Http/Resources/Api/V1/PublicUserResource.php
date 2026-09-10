<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Orang lain — dilihat pemberi kerja saat menimbang penawaran.
 *
 * Batas pengungkapan: nomor HP, email, alamat, dan foto verifikasi TIDAK
 * pernah keluar dari sini. Yang keluar hanya bahan pertimbangan.
 *
 * Dua tambahan sejak profil pekerja dipisah, keduanya sengaja dipilih pada
 * sisi yang lebih tertutup:
 *
 * - **`age`, bukan `birth_date`.** Umur adalah bahan pertimbangan yang wajar
 *   (pekerjaan angkat-angkut, jaga malam); tanggal lahir persis adalah bahan
 *   pembobolan identitas — ia dipakai bank dan layanan publik sebagai
 *   verifikasi. Yang butuh tanggalnya cuma pemiliknya sendiri dan pengelola.
 * - **`work_area` sebatas kota**, tanpa jalan dan tanpa koordinat. Pemberi
 *   kerja perlu tahu si pekerja bersedia berangkat ke mana; ia tidak perlu
 *   tahu di rumah mana orangnya tidur. Koordinat lokasi kerja tidak pernah
 *   keluar dari sini.
 *
 * @mixin User
 */
final class PublicUserResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $worker = $this->resource->workerProfileOrNew();
        $address = $worker->resolvedAddress();

        return [
            'id' => $this->ulid,
            // Nama yang ia tampilkan sebagai pekerja kalau ada, kalau tidak
            // nama akunnya. Satu tempat yang memutuskan: UserWorker.
            'name' => $worker->resolvedName(),
            'gender' => $this->gender?->value,
            'age' => $this->age,
            'avatar_url' => $this->publicUrl($worker->resolvedAvatarPath()),
            'bio' => $this->bio,
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            'city' => $this->city,
            'province' => $this->province,
            // Badge terverifikasi: dari withCount di Action, atau dihitung.
            'identity_verified' => $this->identityVerified(),
            // Terdaftar sebagai pekerja. Dihitung dari ada-tidaknya profil
            // pekerja, bukan kolom — lihat User::readyToWork().
            'ready_to_work' => $this->resource->readyToWork(),
            'as_worker' => [
                'rating_avg' => (float) $worker->worker_rating_avg,
                'rating_count' => $worker->worker_rating_count,
                'tasks_completed' => $worker->tasks_completed,
                // Sejauh apa ia bersedia berangkat, dan dari kota mana.
                // Tanpa jalan, tanpa koordinat — lihat catatan kelas.
                'work_area' => [
                    'city' => $address['city'],
                    'province' => $address['province'],
                    'radius_km' => $worker->radius_km,
                ],
            ],
            'as_poster' => [
                'rating_avg' => (float) $this->poster_rating_avg,
                'rating_count' => $this->poster_rating_count,
                'tasks_posted' => $this->tasks_posted,
            ],
            'member_since' => $this->iso($this->created_at),
        ];
    }

    private function identityVerified(): bool
    {
        // identity_verified_count datang dari withCount() kalau di-eager load;
        // kalau tidak, jatuh ke query. Menghindari N+1 tanpa memaksa.
        return isset($this->identity_verified_count)
            ? $this->identity_verified_count > 0
            : $this->resource->isIdentityVerified();
    }
}
