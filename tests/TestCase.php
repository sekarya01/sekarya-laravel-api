<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Admin\Payment\ConfirmPaymentAction;
use App\Actions\Auth\IssueVerificationCodeAction;
use App\Actions\Payment\ReportTransferAction;
use App\Enums\AdminRole;
use App\Enums\BidStatus;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Admin;
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
use Illuminate\Support\Collection;
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
     * Pengelola biasa (peran `admin`).
     *
     * Dipakai untuk hampir semua test pengelola. `superAdmin()` hanya dipakai
     * ketika yang diuji memang kewenangan super_admin — perannya boleh
     * segalanya, jadi test izin yang memakainya akan lulus untuk alasan yang
     * salah.
     */
    protected function activeAdmin(array $attributes = []): Admin
    {
        return Admin::factory()->create($attributes);
    }

    /**
     * super_admin — dan yang sudah ada dipakai ulang kalau ada.
     *
     * Basis data hanya menerima SATU baris super_admin (indeks unique atas
     * kolom turunan `super_admin_lock`). Suite ini mencampur RefreshDatabase
     * dengan DatabaseTruncation, jadi baris ter-commit dari kelas truncation
     * bisa masih ada saat kelas lain berjalan — dan `create()` polos akan
     * gagal #1062 dengan pesan yang tidak menjelaskan apa pun.
     */
    protected function superAdmin(array $attributes = []): Admin
    {
        $existing = Admin::query()->where('role', AdminRole::SuperAdmin)->first();

        if ($existing instanceof Admin) {
            return $existing;
        }

        return Admin::factory()->superAdmin()->create($attributes);
    }

    /** Bertindak sebagai pengelola memakai access token pengelola sungguhan. */
    protected function asAdmin(Admin $admin): static
    {
        $token = app(TokenIssuer::class)->issuePair($admin)['access']->plainTextToken;

        return $this->authenticateWith($token);
    }

    /** Header long_lived pengelola — hanya boleh untuk /admin/auth/refresh. */
    protected function asAdminWithLongLived(Admin $admin): static
    {
        $token = app(TokenIssuer::class)->issuePair($admin)['long_lived']->plainTextToken;

        return $this->authenticateWith($token);
    }

    /**
     * Buka activity lewat JALUR NYATA: pemberi kerja melapor, pengelola
     * mengonfirmasi.
     *
     * Dulu satu pemanggilan HoldPaymentAction. Sekarang dua langkah dengan dua
     * aktor berbeda, dan fixture yang melompati salah satunya akan menguji
     * keadaan yang tidak bisa dicapai aplikasi — persis kelas bug yang
     * membuat aturan "tidak ada activity tanpa dana ditahan" pernah bisa
     * dilewati.
     *
     * @return Collection<int, Activity>
     */
    protected function openActivities(Task $task, User $poster, ?Admin $admin = null): Collection
    {
        app(ReportTransferAction::class)->handle($task, $poster);

        return app(ConfirmPaymentAction::class)->handle(
            $task->payment()->firstOrFail(),
            $admin ?? $this->activeAdmin(),
        );
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
