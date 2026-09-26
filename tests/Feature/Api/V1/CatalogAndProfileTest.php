<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserActiveMode;
use App\Models\Category;
use App\Models\CategoryCityPrice;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserVerification;
use App\Support\VerificationDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CatalogAndProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    // ── katalog ─────────────────────────────────────────────────────────────

    public function test_categories_require_a_token(): void
    {
        $this->getJson(route('v1.categories.index'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_categories_expose_the_reference_price_contract(): void
    {
        $this->asUser($this->activeUser())
            ->getJson(route('v1.categories.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'slug', 'name', 'description', 'icon', 'reference_price' => [
                    'min', 'max', 'median', 'sample_size', 'from_real_data', 'computed_at',
                ]]],
            ])
            ->assertJsonPath('data.0.reference_price.from_real_data', false)
            ->assertJsonPath('data.0.reference_price.sample_size', 0);
    }

    public function test_from_real_data_flips_once_there_is_data(): void
    {
        Category::query()->where('slug', 'mencuci')->update([
            'ref_sample_size' => 240,
            'ref_computed_at' => now(),
        ]);

        $response = $this->asUser($this->activeUser())->getJson(route('v1.categories.index'));

        $mencuci = collect($response->json('data'))->firstWhere('slug', 'mencuci');
        $this->assertTrue($mencuci['reference_price']['from_real_data']);
        $this->assertSame(240, $mencuci['reference_price']['sample_size']);
    }

    /** Acuan harga per kota dipakai bila sampel kotanya cukup (U17). */
    public function test_categories_use_the_city_price_when_available(): void
    {
        $category = Category::query()->where('slug', 'mencuci')->sole();

        CategoryCityPrice::query()->create([
            'category_id' => $category->getKey(),
            'city' => 'Kota Bandung',
            'ref_price_min' => 80_000,
            'ref_price_max' => 250_000,
            'ref_price_median' => 120_000,
            'ref_sample_size' => 9,
            'ref_computed_at' => now(),
        ]);

        $rows = $this->asUser($this->activeUser())
            ->getJson(route('v1.categories.index', ['city' => 'Kota Bandung']))
            ->assertOk()
            ->json('data');

        $mencuci = collect($rows)->firstWhere('slug', 'mencuci');
        $this->assertSame(120_000, $mencuci['reference_price']['median']);

        // Kota lain tidak punya barisnya → jatuh ke angka nasional (bukan 120rb).
        $lain = $this->asUser($this->activeUser())
            ->getJson(route('v1.categories.index', ['city' => 'Kota Surabaya']))
            ->assertOk()
            ->json('data');
        $this->assertNotSame(120_000, collect($lain)->firstWhere('slug', 'mencuci')['reference_price']['median']);
    }

    public function test_skills_are_listed(): void
    {
        $this->asUser($this->activeUser())
            ->getJson(route('v1.skills.index'))
            ->assertOk()
            ->assertJsonStructure(['data' => [['slug', 'name']]]);
    }

    public function test_skills_can_be_filtered_by_category(): void
    {
        $category = Category::query()->where('slug', 'bersih-rumah')->firstOrFail();

        $slugs = $this->asUser($this->activeUser())
            ->getJson(route('v1.skills.index', ['category_id' => $category->getKey()]))
            ->assertOk()
            ->json('data.*.slug');

        $this->assertContains('cuci-ac', $slugs);
        $this->assertNotContains('jaga-kucing', $slugs);
    }

    public function test_skills_reject_an_unknown_category(): void
    {
        $this->asUser($this->activeUser())
            ->getJson(route('v1.skills.index', ['category_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    // ── profil ──────────────────────────────────────────────────────────────

    public function test_me_returns_the_full_own_profile(): void
    {
        $user = $this->activeUser(['city' => 'Jakarta', 'postal_code' => '10110']);

        $this->asUser($user)
            ->getJson(route('v1.me.show'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->ulid)
            ->assertJsonPath('data.domicile.city', 'Jakarta')
            ->assertJsonPath('data.domicile.postal_code', '10110')
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'phone', 'email', 'phone_verified', 'avatar_url', 'bio',
                    'skills', 'active_mode', 'status', 'theme',
                    'domicile' => ['address_line', 'city', 'province', 'postal_code'],
                    'as_worker' => ['rating_avg', 'rating_count', 'tasks_completed', 'bids_won'],
                    'as_poster' => ['rating_avg', 'rating_count', 'tasks_posted'],
                    'cancellations', 'created_at',
                ],
            ]);
    }

    public function test_me_never_leaks_the_password_hash(): void
    {
        $response = $this->asUser($this->activeUser())->getJson(route('v1.me.show'));

        $this->assertArrayNotHasKey('password', $response->json('data'));
        $this->assertStringNotContainsString('$2y$', $response->getContent());
    }

    public function test_update_profile_changes_only_what_was_sent(): void
    {
        $user = $this->activeUser(['bio' => 'lama', 'city' => 'Jakarta']);

        $this->asUser($user)
            ->patchJson(route('v1.me.update'), ['bio' => 'baru'])
            ->assertOk()
            ->assertJsonPath('data.bio', 'baru')
            ->assertJsonPath('data.domicile.city', 'Jakarta');
    }

    public function test_update_profile_syncs_skills_by_slug(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)
            ->patchJson(route('v1.me.update'), ['skills' => ['cuci-ac', 'setrika']])
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['cuci-ac', 'setrika'],
            $user->refresh()->skills()->pluck('slug')->all(),
        );
    }

    public function test_updating_skills_replaces_rather_than_appends(): void
    {
        $user = $this->activeUser();
        $user->skills()->sync(Skill::query()->whereIn('slug', ['cuci-ac'])->pluck('id'));

        $this->asUser($user)->patchJson(route('v1.me.update'), ['skills' => ['setrika']])->assertOk();

        $this->assertSame(['setrika'], $user->refresh()->skills()->pluck('slug')->all());
    }

    public function test_update_profile_rejects_an_unknown_skill(): void
    {
        $this->asUser($this->activeUser())
            ->patchJson(route('v1.me.update'), ['skills' => ['bukan-skill']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['skills.0']);
    }

    public function test_update_profile_can_switch_the_active_mode(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)
            ->patchJson(route('v1.me.update'), ['active_mode' => UserActiveMode::Working->value])
            ->assertOk()
            ->assertJsonPath('data.active_mode', 'working');
    }

    public function test_update_profile_validates_the_theme(): void
    {
        $this->asUser($this->activeUser())
            ->patchJson(route('v1.me.update'), ['theme' => 'neon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['theme']);
    }

    public function test_update_profile_validates_lengths(): void
    {
        $this->asUser($this->activeUser())
            ->patchJson(route('v1.me.update'), ['name' => str_repeat('a', 200)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ── verifikasi identitas ────────────────────────────────────────────────

    /**
     * Unggah dua dokumen identitas (G2a) lalu susun payload pengajuan.
     * Path-nya harus milik `$user` — penyerahan memeriksa kepemilikan.
     */
    private function identityPayload(User $user): array
    {
        Storage::fake(VerificationDocuments::DISK);

        $idPath = $this->asUser($user)
            ->postJson(route('v1.me.verifications.documents.store'), [
                'file' => UploadedFile::fake()->image('ktp.jpg', 900, 560)->size(200),
            ])
            ->assertCreated()
            ->json('data.path');

        $selfiePath = $this->asUser($user)
            ->postJson(route('v1.me.verifications.documents.store'), [
                'file' => UploadedFile::fake()->image('selfie.jpg', 640, 800)->size(200),
            ])
            ->assertCreated()
            ->json('data.path');

        return [
            'type' => 'identity',
            'id_card_photo_path' => $idPath,
            'selfie_photo_path' => $selfiePath,
            'document_number' => '3174012345678901',
            'name_on_document' => 'Budi Prasetyo',
        ];
    }

    public function test_submitting_identity_verification(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)
            ->postJson(route('v1.me.verifications.store'), $this->identityPayload($user))
            ->assertAccepted()
            ->assertJsonPath('data.type', 'identity')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_verified', false)
            ->assertJsonPath('data.has_id_card_photo', true)
            ->assertJsonPath('data.has_selfie_photo', true);
    }

    /** Dokumen milik orang lain ditolak — verifikasi harus tidak bisa dipalsukan (G2a). */
    public function test_identity_verification_rejects_a_document_owned_by_someone_else(): void
    {
        Storage::fake(VerificationDocuments::DISK);
        $payload = $this->identityPayload($this->activeUser());

        $this->asUser($this->activeUser())
            ->postJson(route('v1.me.verifications.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'verification_document_invalid');
    }

    /** Path foto, NIK, dan rekening tidak boleh pernah keluar dari API. */
    public function test_verification_never_leaks_paths_or_the_id_number(): void
    {
        $user = $this->activeUser();
        $this->asUser($user)->postJson(route('v1.me.verifications.store'), $this->identityPayload($user))->assertAccepted();

        $body = $this->asUser($user)->getJson(route('v1.me.verifications.index'))->getContent();

        foreach (['verifications/', 'ktp.jpg', 'selfie.jpg', '3174012345678901', 'photo_path', 'document_number'] as $secret) {
            $this->assertStringNotContainsString($secret, $body, $secret.' bocor');
        }
    }

    public function test_identity_verification_requires_its_fields(): void
    {
        $this->asUser($this->activeUser())
            ->postJson(route('v1.me.verifications.store'), ['type' => 'identity'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'id_card_photo_path', 'selfie_photo_path', 'document_number', 'name_on_document',
            ]);
    }

    public function test_identity_verification_requires_a_16_digit_number(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)
            ->postJson(route('v1.me.verifications.store'), [...$this->identityPayload($user), 'document_number' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document_number']);
    }

    public function test_bank_account_verification_requires_its_own_fields(): void
    {
        $this->asUser($this->activeUser())
            ->postJson(route('v1.me.verifications.store'), ['type' => 'bank_account'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_code', 'account_number', 'account_holder_name']);
    }

    public function test_submitting_bank_account_verification(): void
    {
        $this->asUser($this->activeUser())
            ->postJson(route('v1.me.verifications.store'), [
                'type' => 'bank_account',
                'bank_code' => 'bca',
                'account_number' => '1234567890',
                'account_holder_name' => 'Budi Prasetyo',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.type', 'bank_account')
            ->assertJsonPath('data.bank_code', 'bca')
            ->assertJsonPath('data.account_holder_name', 'Budi Prasetyo');
    }

    public function test_verification_rejects_an_unknown_type(): void
    {
        $this->asUser($this->activeUser())
            ->postJson(route('v1.me.verifications.store'), ['type' => 'sidik-jari'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_resubmitting_replaces_the_pending_request(): void
    {
        $user = $this->activeUser();
        $payload = $this->identityPayload($user);
        $this->asUser($user)->postJson(route('v1.me.verifications.store'), $payload)->assertAccepted();
        $this->asUser($user)->postJson(route('v1.me.verifications.store'), [
            ...$payload,
            'name_on_document' => 'Budi P',
        ])->assertAccepted();

        $this->assertSame(1, UserVerification::query()->where('user_id', $user->getKey())->count());
        $this->assertSame('Budi P', UserVerification::query()->where('user_id', $user->getKey())->value('name_on_document'));
    }

    public function test_a_verified_request_is_kept_as_history(): void
    {
        $user = $this->activeUser();
        UserVerification::factory()->verified()->create(['user_id' => $user->getKey()]);

        $this->asUser($user)->postJson(route('v1.me.verifications.store'), $this->identityPayload($user))->assertAccepted();

        $this->assertSame(2, UserVerification::query()->where('user_id', $user->getKey())->count());
    }

    public function test_verifications_are_listed_newest_first(): void
    {
        $user = $this->activeUser();
        UserVerification::factory()->verified()->create([
            'user_id' => $user->getKey(),
            'submitted_at' => now()->subDay(),
        ]);
        $this->asUser($user)->postJson(route('v1.me.verifications.store'), $this->identityPayload($user))->assertAccepted();

        $statuses = $this->asUser($user)
            ->getJson(route('v1.me.verifications.index'))
            ->assertOk()
            ->json('data.*.status');

        $this->assertSame(['pending', 'verified'], $statuses);
    }

    public function test_verified_identity_shows_up_on_the_public_profile(): void
    {
        $user = $this->activeUser();
        UserVerification::factory()->verified()->create(['user_id' => $user->getKey()]);

        $this->assertTrue($user->refresh()->isIdentityVerified());
    }
}
