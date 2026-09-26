<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletTopupStatus;
use App\Models\Concerns\HasUlid;
use Database\Factories\WalletTopupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan isi saldo.
 *
 * `status` TIDAK mass-assignable, satu aturan dengan `users.status` dan
 * `admins.role`: kolom yang menentukan apakah uang jadi bertambah tidak boleh
 * datang dari larik atribut, dan bawaan kolomnya adalah keadaan yang paling
 * tidak menguntungkan pengirimnya (`awaiting_confirmation` — belum ada uang
 * yang dianggap masuk).
 *
 * @property-read WalletTopupStatus $status
 */
final class WalletTopup extends Model
{
    /** @use HasFactory<WalletTopupFactory> */
    use HasFactory, HasUlid;

    /** Sengaja TANPA `status`. */
    protected $fillable = ['user_id', 'amount', 'sender_note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // Kunci pemilik DI-CAST, dan itu bukan kosmetik.
            //
            // Policy membandingkannya dengan `$user->getKey()` memakai `===`.
            // Laravel meng-cast primary key model sendiri ke int (getCasts()
            // menggabungkan keyName => keyType), tapi kolom asing seperti ini
            // tidak dicast apa pun — nilainya apa adanya dari driver. Begitu
            // driver mengembalikannya sebagai string, `int === string` bernilai
            // false dan PEMILIK ASLI ditolak 403, sementara kueri yang
            // membandingkannya di SQL tetap lolos karena MySQL menyamakan tipe.
            // Persis itu yang terjadi di produksi: `GET /tasks/posted` berisi
            // task orangnya, tapi `PUT /tasks/{task}` menjawab 403.
            'user_id' => 'integer',
            'status' => WalletTopupStatus::class,
            'amount' => 'integer',
            'unique_code' => 'integer',
            'transfer_amount' => 'integer',
            'reviewed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /**
     * Antrean pengelola: paling lama menunggu di depan.
     *
     * `created_at`, bukan `reported_at` seperti di `payments` — di sini
     * permintaannya ADALAH laporannya, jadi keduanya waktu yang sama dan
     * kolom kedua cuma akan jadi tempat kedua yang bisa melenceng.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeQueueOrder(Builder $query): void
    {
        $query->orderBy('created_at')->orderBy('id');
    }

    /** @param  Builder<$this>  $query */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
