<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\UserWorkerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sisi PEKERJA dari sebuah akun — satu baris per orang, dibuat saat ia
 * pertama kali bekerja atau pertama kali mengisi profil pekerjanya.
 *
 * Kelas ini adalah SATU-SATUNYA tempat aturan "NULL berarti pakai punya akun"
 * ditulis. Setiap Resource memanggil method di bawah, tidak pernah membaca
 * kolomnya langsung — kalau resolusinya diulang di tiap transformer, satu yang
 * lupa akan mengeluarkan `null` di tempat yang seharusnya berisi nama orang,
 * dan itu terlihat seperti data yang hilang, bukan seperti bug.
 *
 * Agregat reputasi (`worker_rating_avg`, `tasks_completed`, `bids_won`)
 * sengaja TIDAK mass-assignable. Sama alasannya dengan `users.status`: angka
 * yang dipakai pemberi kerja untuk memilih orang tidak boleh bisa datang dari
 * payload. Yang menulisnya hanya Action, lewat `increment()` atau perhitungan
 * ulang dari tabel `reviews`.
 */
class UserWorker extends Model
{
    /** @use HasFactory<UserWorkerFactory> */
    use HasFactory;

    /**
     * Agregat reputasi TIDAK ada di sini, dan itu disengaja.
     *
     * @var list<string>
     */
    protected $fillable = [
        'display_name', 'contact_phone', 'avatar_path',
        'address_line', 'city', 'province', 'postal_code',
        'latitude', 'longitude', 'radius_km',
    ];

    /**
     * Nilai bawaan yang sama dengan bawaan kolomnya.
     *
     * Ada supaya instance yang BELUM tersimpan (User::workerProfileOrNew())
     * mengeluarkan angka nol, bukan `null`. Tanpa ini, profil pekerja yang
     * belum pernah dibuat akan tampil sebagai `"rating_count": null` di API —
     * dan klien yang menghitung akan pecah pada orang yang belum pernah
     * bekerja, yaitu justru setiap pengguna baru.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'worker_rating_avg' => 0,
        'worker_rating_count' => 0,
        'tasks_completed' => 0,
        'bids_won' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_km' => 'integer',
            'worker_rating_avg' => 'decimal:2',
            'worker_rating_count' => 'integer',
            'tasks_completed' => 'integer',
            'bids_won' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Keyset ordering. `id` wajib sebagai tiebreaker — tanpa itu cursor bisa
     * melewati atau mengulang baris saat beberapa profil dibuat pada detik
     * yang sama, dan itu justru yang terjadi saat data diimpor.
     *
     * Yang diurutkan `user_workers.created_at`, BUKAN `users.created_at`:
     * yang dicari pemberi kerja adalah orang yang baru siap bekerja, bukan
     * orang yang baru mendaftar. Seseorang bisa punya akun dua tahun lalu dan
     * baru kemarin membuka profil pekerjanya.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('user_workers.created_at')->orderByDesc('user_workers.id');
    }

    /**
     * Penyaring alamat, SISI SQL dari `resolvedAddress()`.
     *
     * Dua encoding untuk satu aturan, dan itu memang risikonya — tapi PHP
     * tidak bisa menyaring puluhan ribu baris di basis data, dan SQL tidak
     * bisa dipakai transformer. Kalau salah satunya diubah, ubah keduanya:
     * `tests/Unit/Models/UserWorkerModelTest.php` menguji versi PHP-nya dan
     * `tests/Feature/Api/V1/User/WorkerListApiTest.php` versi SQL-nya, dengan
     * fixture yang sama persis.
     *
     * Menyaring `user_workers.city` saja akan MENGHILANGKAN setiap pekerja
     * yang belum pernah menimpa alamatnya — yaitu hampir semuanya, karena
     * alamat kerja bawaannya diwarisi dari akun. Daftarnya akan tampak bekerja
     * dan hampir selalu kosong.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeWhereResolvedAddress(Builder $query, string $column, string $value): void
    {
        // Hanya kolom yang benar-benar ada di KEDUA tabel. Nilai dari klien
        // tidak pernah sampai ke nama kolom, tapi daftar ini yang membuatnya
        // tidak bisa terjadi walau pemanggilnya salah.
        if (! in_array($column, ['city', 'province'], true)) {
            throw new \InvalidArgumentException("kolom alamat tidak dikenal: {$column}");
        }

        $query->where(function (Builder $q) use ($column, $value): void {
            $q
                // Punya nilainya sendiri.
                ->where('user_workers.'.$column, $value)
                // Atau tidak punya alamat sendiri sama sekali, jadi mewarisi
                // domisili akun — seluruhnya, bukan per kolom.
                ->orWhere(fn (Builder $inherited) => $inherited
                    ->whereNull('user_workers.address_line')
                    ->whereNull('user_workers.city')
                    ->whereNull('user_workers.province')
                    ->whereNull('user_workers.postal_code')
                    ->whereHas('user', fn (Builder $u) => $u->where($column, $value)));
        });
    }

    // ── Resolusi: kolom sendiri kalau diisi, kalau tidak jatuh ke akun ──────
    //
    // `$this->user` bisa null hanya pada instance yang belum tersimpan
    // (lihat User::workerProfileOrNew(), yang selalu memasang relasinya).

    public function resolvedName(): ?string
    {
        return $this->display_name ?? $this->user?->name;
    }

    public function resolvedPhone(): ?string
    {
        return $this->contact_phone ?? $this->user?->phone;
    }

    public function resolvedAvatarPath(): ?string
    {
        return $this->avatar_path ?? $this->user?->avatar_path;
    }

    /**
     * Alamat diresolusi sebagai SATU KESATUAN, bukan per kolom.
     *
     * Kalau tiap kolom jatuh sendiri-sendiri ke akun, pekerja yang menuliskan
     * alamat kerjanya di kota lain akan mendapat gabungan dua alamat: jalannya
     * dari profil pekerja, kotanya dari domisili akun. Itu alamat yang tidak
     * pernah ada, dan pemberi kerja akan mendatanginya.
     *
     * @return array{address_line: ?string, city: ?string, province: ?string, postal_code: ?string}
     */
    public function resolvedAddress(): array
    {
        return $this->hasOwnAddress()
            ? [
                'address_line' => $this->address_line,
                'city' => $this->city,
                'province' => $this->province,
                'postal_code' => $this->postal_code,
            ]
            : [
                'address_line' => $this->user?->address_line,
                'city' => $this->user?->city,
                'province' => $this->user?->province,
                'postal_code' => $this->user?->postal_code,
            ];
    }

    /** Satu kolom alamat terisi sudah cukup: alamatnya miliknya sendiri. */
    public function hasOwnAddress(): bool
    {
        return $this->address_line !== null
            || $this->city !== null
            || $this->province !== null
            || $this->postal_code !== null;
    }

    /**
     * Identitas, selalu dari akun.
     *
     * Tidak ada kolomnya di tabel ini — orang tidak berganti jenis kelamin
     * atau tanggal lahir saat berpindah dari mode memberi kerja ke mencari
     * kerja.
     */
    public function gender(): ?Gender
    {
        return $this->user?->gender;
    }

    public function age(): ?int
    {
        return $this->user?->age;
    }
}
