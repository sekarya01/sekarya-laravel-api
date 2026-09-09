<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\TaskStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Activity;
use App\Models\Bid;
use App\Models\Category;
use App\Models\EmailVerificationCode;
use App\Models\Payment;
use App\Models\Skill;
use App\Models\Task;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ModelsTest extends TestCase
{
    use RefreshDatabase;

    private function task(array $attributes = []): Task
    {
        $this->seedReference();

        return Task::factory()->create([
            'poster_id' => $this->activeUser()->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            ...$attributes,
        ]);
    }

    // ── HasUlid ─────────────────────────────────────────────────────────────

    public function test_ulid_is_generated_for_every_model_that_needs_one(): void
    {
        $this->seedReference();
        $user = $this->activeUser();
        $task = $this->task();
        $bid = Bid::factory()->create(['task_id' => $task->getKey(), 'bidder_id' => $user->getKey()]);
        $payment = Payment::factory()->create(['task_id' => $task->getKey(), 'payer_id' => $user->getKey()]);

        foreach ([$user, $task, $bid, $payment] as $model) {
            $this->assertSame(26, strlen((string) $model->ulid), $model::class);
        }
    }

    /** Ulid diisi lewat hook; kalau hook mati, kolom NOT NULL akan gagal. */
    public function test_ulid_is_not_overwritten_when_provided(): void
    {
        $this->seedReference();
        $given = (string) Str::ulid();

        $task = Task::factory()->create([
            'poster_id' => $this->activeUser()->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'ulid' => $given,
        ]);

        $this->assertSame($given, $task->ulid);
    }

    public function test_route_key_is_the_ulid(): void
    {
        $this->assertSame('ulid', (new Task)->getRouteKeyName());
        $this->assertSame('ulid', (new Bid)->getRouteKeyName());
        $this->assertSame('ulid', (new Payment)->getRouteKeyName());
        $this->assertSame('ulid', (new Activity)->getRouteKeyName());
        $this->assertSame('ulid', (new User)->getRouteKeyName());
        $this->assertSame('slug', (new Category)->getRouteKeyName());
        $this->assertSame('slug', (new Skill)->getRouteKeyName());
    }

    // ── Task ────────────────────────────────────────────────────────────────

    /** budget_max nullable: perbandingan harus menoleransi null. */
    public function test_is_within_budget_treats_null_max_as_no_ceiling(): void
    {
        $task = $this->task(['budget_min' => 100_000, 'budget_max' => null]);

        $this->assertFalse($task->isWithinBudget(99_999));
        $this->assertTrue($task->isWithinBudget(100_000));
        $this->assertTrue($task->isWithinBudget(999_999_999));
    }

    public function test_is_within_budget_respects_an_explicit_max(): void
    {
        $task = $this->task(['budget_min' => 100_000, 'budget_max' => 200_000]);

        $this->assertTrue($task->isWithinBudget(150_000));
        $this->assertTrue($task->isWithinBudget(200_000));
        $this->assertFalse($task->isWithinBudget(200_001));
    }

    public function test_bidding_closed_detection(): void
    {
        $this->assertFalse($this->task(['bidding_closes_at' => null])->isBiddingClosed());
        $this->assertFalse($this->task(['bidding_closes_at' => now()->addHour()])->isBiddingClosed());
        $this->assertTrue($this->task(['bidding_closes_at' => now()->subHour()])->isBiddingClosed());
    }

    public function test_biddable_scope_requires_open_and_an_open_window(): void
    {
        $open = $this->task(['status' => TaskStatus::Open]);
        $draft = $this->task(['status' => TaskStatus::Draft]);
        $closed = $this->task(['status' => TaskStatus::Open, 'bidding_closes_at' => now()->subDay()]);

        // Dilingkupi ke baris milik test ini. Test tidak boleh mengandaikan
        // tabelnya kosong — suite ini mencampur RefreshDatabase (transaksi)
        // dengan DatabaseTruncation (commit) untuk test FULLTEXT, jadi bisa
        // ada baris ter-commit dari kelas lain.
        $ids = Task::query()
            ->biddable()
            ->whereIn('id', [$open->getKey(), $draft->getKey(), $closed->getKey()])
            ->pluck('id')
            ->all();

        $this->assertSame([$open->getKey()], $ids);
    }

    public function test_latest_first_scope_orders_by_created_then_id(): void
    {
        $a = $this->task(['created_at' => now()->subDay()]);
        $b = $this->task(['created_at' => now()]);

        $ids = Task::query()
            ->latestFirst()
            ->whereIn('id', [$a->getKey(), $b->getKey()])
            ->pluck('id')
            ->all();

        $this->assertSame([$b->getKey(), $a->getKey()], $ids);
    }

    public function test_task_number_is_generated(): void
    {
        $this->assertMatchesRegularExpression('/^TK-\d{6}-[A-Z0-9]{6}$/', $this->task()->task_number);
    }

    public function test_task_relations_resolve(): void
    {
        $task = $this->task();
        $this->assertNotNull($task->poster);
        $this->assertNotNull($task->category);
        $this->assertNull($task->worker);
        $this->assertCount(0, $task->bids);
        $this->assertCount(0, $task->skills);
        $this->assertCount(0, $task->reviews);
        $this->assertCount(0, $task->statusLogs);
        $this->assertNull($task->payment);
        $this->assertNull($task->activity);
        $this->assertNull($task->acceptedBid);
        $this->assertNull($task->myBid);
    }

    // ── User ────────────────────────────────────────────────────────────────

    public function test_identity_verified_is_computed_not_stored(): void
    {
        $user = $this->activeUser();
        $this->assertFalse($user->isIdentityVerified());

        UserVerification::factory()->verified()->create(['user_id' => $user->getKey()]);
        $this->assertTrue($user->refresh()->isIdentityVerified());
    }

    /** Status bisa dicabut, jadi badge tidak boleh disimpan sebagai boolean. */
    public function test_revoked_verification_stops_counting(): void
    {
        $user = $this->activeUser();
        $verification = UserVerification::factory()->verified()->create(['user_id' => $user->getKey()]);
        $this->assertTrue($user->isIdentityVerified());

        $verification->forceFill(['status' => VerificationStatus::Revoked])->save();

        $this->assertFalse($user->refresh()->isIdentityVerified());
    }

    public function test_bank_verification_does_not_grant_identity(): void
    {
        $user = $this->activeUser();
        UserVerification::factory()->verified()->create([
            'user_id' => $user->getKey(),
            'type' => VerificationType::BankAccount,
        ]);

        $this->assertFalse($user->refresh()->isIdentityVerified());
    }

    public function test_user_relations_resolve(): void
    {
        $this->seedReference();
        $user = $this->activeUser();

        $this->assertCount(0, $user->verifications);
        $this->assertCount(0, $user->postedTasks);
        $this->assertCount(0, $user->workedTasks);
        $this->assertCount(0, $user->bids);
        $this->assertCount(0, $user->activities);
        $this->assertCount(0, $user->receivedReviews);
        $this->assertCount(0, $user->skills);
    }

    public function test_password_is_hashed_by_cast(): void
    {
        $user = User::factory()->create(['password' => 'RahasiaKuat2026']);

        $this->assertTrue(Hash::check('RahasiaKuat2026', $user->password));
    }

    public function test_password_and_token_are_hidden(): void
    {
        $array = $this->activeUser()->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    // ── Category & Skill ────────────────────────────────────────────────────

    public function test_category_reports_whether_price_data_is_real(): void
    {
        $this->seedReference();
        $category = $this->anyCategory();

        $this->assertFalse($category->hasRealPriceData());

        $category->forceFill(['ref_sample_size' => 12])->save();
        $this->assertTrue($category->refresh()->hasRealPriceData());
    }

    public function test_skill_relations_resolve(): void
    {
        $this->seedReference();
        $skill = $this->skill('cuci-ac');

        $this->assertNotNull($skill->category);
        $this->assertCount(0, $skill->tasks);
        $this->assertCount(0, $skill->users);
    }

    // ── EmailVerificationCode ───────────────────────────────────────────────

    private function code(array $attributes = []): EmailVerificationCode
    {
        return EmailVerificationCode::query()->create([
            'user_id' => $this->activeUser()->getKey(),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
            'last_sent_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_a_fresh_code_is_usable(): void
    {
        $code = $this->code();

        $this->assertTrue($code->isUsable());
        $this->assertFalse($code->isExpired());
        $this->assertFalse($code->isConsumed());
        $this->assertFalse($code->attemptsExhausted());
    }

    public function test_an_expired_code_is_unusable(): void
    {
        $code = $this->code(['expires_at' => now()->subMinute()]);

        $this->assertTrue($code->isExpired());
        $this->assertFalse($code->isUsable());
    }

    public function test_a_consumed_code_is_unusable(): void
    {
        $code = $this->code();
        $code->forceFill(['consumed_at' => now()])->save();

        $this->assertTrue($code->isConsumed());
        $this->assertFalse($code->isUsable());
    }

    public function test_an_exhausted_code_is_unusable(): void
    {
        $code = $this->code(['attempts' => 5]);

        $this->assertTrue($code->attemptsExhausted());
        $this->assertFalse($code->isUsable());
    }

    public function test_usable_scope_matches_the_helper(): void
    {
        $usable = $this->code();
        $this->code(['expires_at' => now()->subMinute()]);
        $this->code(['attempts' => 5]);

        $this->assertSame([$usable->getKey()], EmailVerificationCode::query()->usable()->pluck('id')->all());
    }

    public function test_resend_cooldown_countdown(): void
    {
        $this->assertSame(0, $this->code(['last_sent_at' => null])->secondsUntilResendAllowed());
        $this->assertSame(0, $this->code(['last_sent_at' => now()->subMinutes(5)])->secondsUntilResendAllowed());
        $this->assertGreaterThan(0, $this->code(['last_sent_at' => now()])->secondsUntilResendAllowed());
    }

    public function test_code_hash_is_hidden(): void
    {
        $this->assertArrayNotHasKey('code_hash', $this->code()->toArray());
    }

    // ── UserVerification ────────────────────────────────────────────────────

    /** NIK dan nomor rekening tersimpan terenkripsi, tidak mentah. */
    public function test_sensitive_columns_are_encrypted_at_rest(): void
    {
        $user = $this->activeUser();
        $nik = '3174012345678901';

        $verification = UserVerification::query()->create([
            'user_id' => $user->getKey(),
            'type' => VerificationType::Identity,
            'document_number_hash' => hash('sha256', $nik),
            'document_number_enc' => $nik,
            'submitted_at' => now(),
        ]);

        $raw = (string) \DB::table('user_verifications')
            ->where('id', $verification->getKey())
            ->value('document_number_enc');

        $this->assertStringNotContainsString($nik, $raw, 'NIK tidak boleh tersimpan mentah');
        $this->assertSame($nik, $verification->refresh()->document_number_enc);
    }

    public function test_the_hash_allows_duplicate_detection(): void
    {
        $nik = '3174012345678901';
        $stored = hash('sha256', $nik);

        $this->assertSame($stored, hash('sha256', $nik));
        $this->assertNotSame($stored, hash('sha256', '3174012345678902'));
    }

    public function test_verification_hides_photo_paths_and_secrets(): void
    {
        $array = UserVerification::factory()->create(['user_id' => $this->activeUser()->getKey()])->toArray();

        foreach (['id_card_photo_path', 'selfie_photo_path', 'document_number_hash', 'document_number_enc', 'account_number_enc'] as $key) {
            $this->assertArrayNotHasKey($key, $array, $key.' tidak boleh ikut serialisasi');
        }
    }
}
