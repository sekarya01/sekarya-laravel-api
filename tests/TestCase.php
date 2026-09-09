<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Auth\IssueVerificationCodeAction;
use App\Enums\BidStatus;
use App\Enums\UserStatus;
use App\Models\Bid;
use App\Models\Category;
use App\Models\EmailVerificationCode;
use App\Models\Skill;
use App\Models\Task;
use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use App\Support\TokenIssuer;
use Database\Seeders\CategorySeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * Basis semua test.
 *
 * CATATAN PENTING soal strategi database:
 *
 * Sebagian besar kelas memakai RefreshDatabase (transaksi + rollback, cepat).
 * Kelas yang menguji pencarian FULLTEXT HARUS memakai DatabaseTruncation,
 * karena indeks FULLTEXT InnoDB baru diperbarui setelah transaksi COMMIT —
 * di bawah RefreshDatabase, `MATCH ... AGAINST` tidak akan pernah melihat
 * baris yang dibuat di dalam test.
 *
 * Konsekuensinya kedua strategi bercampur dalam satu suite, dan kelas
 * truncation meninggalkan baris ter-commit. Karena itu:
 *
 *   ASSERTION TIDAK BOLEH MENGANDAIKAN TABELNYA KOSONG.
 *
 * Lingkupi kueri ke baris yang test itu sendiri buat (whereIn id, where
 * user_id, dan seterusnya) alih-alih menghitung seluruh tabel.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Data acuan (kategori & keahlian) yang dibutuhkan hampir semua test.
     *
     * Dipanggil sekali per test yang memerlukannya, bukan di setUp global,
     * supaya test unit yang tidak butuh DB tetap cepat.
     */
    protected function seedReference(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(SkillSeeder::class);
    }

    /** Kategori pertama yang aktif — dipakai saat isi kategori tidak penting. */
    protected function anyCategory(): Category
    {
        return Category::query()->active()->firstOrFail();
    }

    protected function skill(string $slug): Skill
    {
        return Skill::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * Pengguna aktif yang siap dipakai.
     *
     * `status` disetel eksplisit di luar mass assignment karena kolom itu
     * memberi hak akses dan sengaja tidak fillable.
     */
    protected function activeUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->status = UserStatus::Active;
        $user->email_verified_at = now();
        $user->save();

        return $user->refresh();
    }

    /**
     * Rekrut seorang pekerja pada sebuah task.
     *
     * Sejak satu task bisa merekrut banyak orang, "siapa yang mengerjakan"
     * tidak lagi tersimpan di kolom `tasks` — sumbernya baris `bids` berstatus
     * accepted. Helper ini menulis baris itu DAN menyelaraskan penghitung
     * task, supaya fixture tidak menghasilkan keadaan yang tidak mungkin
     * terjadi lewat jalur aplikasi (mis. task dengan pekerja tapi
     * `workers_hired` nol).
     */
    protected function hireWorker(Task $task, User $worker, int $amount = 220_000): Bid
    {
        $bid = Bid::factory()->create([
            'task_id' => $task->getKey(),
            'bidder_id' => $worker->getKey(),
            'amount' => $amount,
            'status' => BidStatus::Accepted,
            'responded_at' => now(),
        ]);

        $accepted = $task->acceptedBids()->get();

        $task->forceFill([
            'workers_hired' => $accepted->count(),
            'agreed_amount' => (int) $accepted->sum('amount'),
            'workers_needed' => max($task->workers_needed, $accepted->count()),
        ])->save();

        return $bid;
    }

    /**
     * Bertindak sebagai pengguna memakai access token sungguhan.
     *
     * `forgetGuards()` WAJIB dipanggil. Guard yang sudah meresolusi pengguna
     * akan me-memoize hasilnya pada instance aplikasi, dan instance itu
     * dipakai ulang untuk seluruh request di dalam satu test. Tanpa flush ini,
     * request kedua tetap dianggap sebagai pengguna yang pertama — sehingga
     * setiap pemeriksaan otorisasi "orang lain harus 403" akan lulus palsu
     * dengan 200.
     */
    protected function asUser(User $user): static
    {
        $token = app(TokenIssuer::class)->issuePair($user)['access']->plainTextToken;

        return $this->authenticateWith($token);
    }

    /** Header long_lived token — hanya boleh untuk /auth/refresh. */
    protected function asUserWithLongLived(User $user): static
    {
        $token = app(TokenIssuer::class)->issuePair($user)['long_lived']->plainTextToken;

        return $this->authenticateWith($token);
    }

    /**
     * Pasang Bearer token dan buang guard yang sudah teresolusi.
     *
     * Namanya sengaja bukan `withToken` — Laravel sudah punya method itu.
     */
    private function authenticateWith(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Terbitkan kode verifikasi lalu kembalikan angkanya.
     *
     * Membutuhkan Notification::fake() sudah aktif. Kode aslinya hanya ada
     * sesaat — di database yang tersimpan cuma hash-nya — jadi satu-satunya
     * cara membacanya adalah dari notifikasi yang dikirim.
     */
    protected function issueVerificationCode(User $user): string
    {
        app(IssueVerificationCodeAction::class)
            ->handle($user, null, enforceCooldown: false);

        $captured = null;

        Notification::assertSentTo(
            $user,
            function (VerificationCodeNotification $notification) use (&$captured): bool {
                $captured = $notification->code;

                return true;
            },
        );

        return (string) $captured;
    }

    /** Ambil kode verifikasi terbaru milik user, dari sisi database. */
    protected function latestVerificationCodeFor(User $user): EmailVerificationCode
    {
        return EmailVerificationCode::query()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->firstOrFail();
    }

    /** Bantuan baca: kode galat domain dari respons. */
    protected function errorCode(TestResponse $response): ?string
    {
        return $response->json('code');
    }
}
