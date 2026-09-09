<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SearchTerms;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Normalisasi kata untuk pencarian nama pekerjaan.
 *
 * Kelas ini dipakai di DUA sisi — saat mengindeks dan saat mencari — dan
 * kegagalannya tidak berisik: kalau kedua sisi tidak sepakat, hasil pencarian
 * cuma kosong. Karena itu sebagian besar test di sini menegaskan KESEPAKATAN
 * antara keduanya, bukan bentuk keluarannya satu per satu.
 */
final class SearchTermsTest extends TestCase
{
    private function terms(): SearchTerms
    {
        return new SearchTerms;
    }

    /** @return list<string> */
    private function indexed(string ...$parts): array
    {
        return explode(' ', $this->terms()->forIndex(...$parts));
    }

    // ── Kesepakatan dua sisi ────────────────────────────────────────────────

    /**
     * Inti dari seluruh kelas: apa yang dicari harus ada di apa yang disimpan.
     */
    #[DataProvider('matchingPairs')]
    public function test_what_is_searched_is_present_in_what_was_indexed(
        string $title,
        string $keyword,
    ): void {
        $indexed = $this->indexed($title);
        $expression = $this->terms()->forQuery($keyword);

        $this->assertNotNull($expression);

        foreach (explode(' ', $expression) as $term) {
            // Buang operator BOOLEAN MODE; sisakan katanya.
            $word = rtrim(ltrim($term, '+'), '*');

            $found = in_array($word, $indexed, true)
                || array_any($indexed, fn (string $t): bool => str_starts_with($t, $word));

            $this->assertTrue($found, sprintf(
                'kata "%s" dari pencarian "%s" tidak ada di indeks judul "%s" (%s)',
                $word,
                $keyword,
                $title,
                implode(' ', $indexed),
            ));
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function matchingPairs(): array
    {
        return [
            'kata utuh' => ['Cuci karpet ruang tamu', 'karpet'],
            'mengetik sebagian' => ['Cuci karpet ruang tamu', 'karp'],
            'dua kata' => ['Cuci karpet ruang tamu', 'cuci karpet'],
            'huruf besar-kecil' => ['Cuci KARPET', 'karpet'],
            'tanda baca di masukan' => ['Cuci karpet', 'cuci, karpet!'],

            // Kata pendek — inti perbaikan pertama.
            'kata dua huruf' => ['Cuci AC 2 unit', 'ac'],
            'kata pendek di tengah' => ['Servis AC rumah', 'servis ac'],

            // Imbuhan — inti perbaikan kedua.
            'awalan me-' => ['Membersihkan gudang', 'bersih'],
            'akhiran -kan' => ['Bersihkan gudang', 'bersih'],
            'awalan + akhiran' => ['Membersihkan gudang', 'bersihkan'],
            'awalan meny-' => ['Menyapu halaman', 'sapu'],
            'awalan men-' => ['Mencuci motor', 'cuci'],
            'awalan pe- + -an' => ['Pekerjaan berat', 'kerja'],
            'awalan ter-' => ['Terpasang rapi', 'pasang'],
        ];
    }

    // ── Kata pendek ─────────────────────────────────────────────────────────

    /**
     * `innodb_ft_min_token_size` bawaan MySQL adalah 3, jadi "ac" tidak akan
     * pernah masuk indeks apa adanya. Bentuk bersentinel-lah yang terindeks.
     */
    public function test_short_words_get_an_indexable_form(): void
    {
        $indexed = $this->indexed('Cuci AC');

        $this->assertContains('ac', $indexed, 'bentuk asli tetap disimpan');
        $this->assertContains('zqac', $indexed, 'bentuk yang panjangnya cukup untuk diindeks');
        $this->assertSame('+zqac', $this->terms()->forQuery('ac'));
    }

    /**
     * Kata pendek dicocokkan PERSIS.
     *
     * Ini yang membedakannya dari parser ngram: di sana "ac" ikut menangkap
     * "macet", "acara", dan "bacaan" — untuk marketplace berbahasa Indonesia,
     * itu hasil yang lebih buruk daripada tidak ketemu sama sekali.
     */
    public function test_a_short_word_does_not_leak_into_longer_words(): void
    {
        $indexed = $this->indexed('Perbaiki macet dan acara');

        $this->assertNotContains('zqac', $indexed);
    }

    /**
     * INVARIAN yang membuat pencarian kata pendek berjalan di server MANA PUN.
     *
     * Sisi kueri tidak pernah mengeluarkan kata yang lebih pendek dari ambang
     * bawaan MySQL — kata pendek selalu diubah jadi bentuk bersentinel yang
     * panjangnya cukup. Karena itu hasilnya tidak bergantung pada
     * `innodb_ft_min_token_size`, dan aplikasi ini tidak menuntut setelan
     * server khusus.
     *
     * Ini diuji sebagai invarian, bukan lewat pencarian sungguhan: mesin
     * pengembangan ini kebetulan menyetel ambangnya ke 1, sehingga sebuah test
     * pencarian "ac" akan lulus bahkan kalau sentinelnya dicopot — dan lulus
     * palsu lebih buruk daripada tidak ada test.
     */
    #[DataProvider('shortWordQueries')]
    public function test_no_query_term_is_shorter_than_the_mysql_minimum(string $keyword): void
    {
        $expression = $this->terms()->forQuery($keyword);

        $this->assertNotNull($expression);

        foreach (explode(' ', $expression) as $term) {
            $word = rtrim(ltrim($term, '+'), '*');

            $this->assertGreaterThanOrEqual(
                SearchTerms::MIN_TOKEN_LENGTH,
                mb_strlen($word),
                sprintf('"%s" dari pencarian "%s" terlalu pendek untuk terindeks', $word, $keyword),
            );
        }
    }

    /** @return array<string, array{0: string}> */
    public static function shortWordQueries(): array
    {
        return [
            'dua huruf' => ['ac'],
            'satu huruf' => ['a'],
            'campur' => ['cuci ac 2 unit'],
            'angka pendek' => ['unit 2'],
            'semua pendek' => ['ac tv pc'],
        ];
    }

    /**
     * Sisi indeks memenuhi janji yang sama: bentuk bersentinel selalu cukup
     * panjang untuk masuk indeks pada server dengan setelan bawaan.
     */
    public function test_every_indexed_short_form_is_long_enough(): void
    {
        $indexed = $this->indexed('Servis AC TV PC di rumah');
        $sentinels = array_filter($indexed, fn (string $t): bool => str_starts_with($t, 'zq'));

        $this->assertNotEmpty($sentinels);

        foreach ($sentinels as $token) {
            $this->assertGreaterThanOrEqual(SearchTerms::MIN_TOKEN_LENGTH, mb_strlen($token));
        }
    }

    public function test_long_words_are_left_alone(): void
    {
        $this->assertSame('+rumah*', $this->terms()->forQuery('rumah'));
    }

    // ── Imbuhan ─────────────────────────────────────────────────────────────

    public function test_the_root_word_is_stored_next_to_the_original(): void
    {
        $indexed = $this->indexed('Membersihkan gudang');

        $this->assertContains('membersihkan', $indexed, 'bentuk aslinya tidak hilang');
        $this->assertContains('bersih', $indexed, 'akarnya ikut, supaya "bersih" menemukannya');
    }

    /**
     * Penggalan HANYA di sisi indeks. Akar yang salah di sana cuma jadi kata
     * yang tak pernah dicari; kalau penggalan juga dipakai di sisi kueri, satu
     * akar yang salah langsung jadi hasil pencarian yang salah.
     */
    public function test_the_query_side_never_stems(): void
    {
        $this->assertSame('+membersihkan*', $this->terms()->forQuery('membersihkan'));
        $this->assertSame('+pekerjaan*', $this->terms()->forQuery('pekerjaan'));
    }

    /** Penggalan yang menyisakan potongan pendek ditolak — itu bukan akar. */
    public function test_it_refuses_to_strip_a_word_down_to_a_fragment(): void
    {
        // "ber" + "sih" -> "sih" terlalu pendek, jadi "bersih" dibiarkan utuh.
        $this->assertSame(['bersih'], $this->indexed('bersih'));

        // "di" + "am" -> "am" terlalu pendek.
        $this->assertNotContains('am', $this->indexed('diam'));
    }

    public function test_stemming_stops_after_one_prefix(): void
    {
        // Mengupas berlapis butuh kamus akar kata untuk tahu kapan berhenti;
        // tanpa itu ia terus mengupas sampai jadi potongan tak berarti.
        $indexed = $this->indexed('mempertanggungjawabkan');

        foreach ($indexed as $token) {
            $this->assertGreaterThanOrEqual(4, mb_strlen($token));
        }
    }

    // ── Batas & masukan aneh ────────────────────────────────────────────────

    /**
     * Masukan yang tidak menyisakan kata berarti NOL hasil, bukan "tanpa
     * filter" — mengembalikan semua task untuk pencarian "???" menyesatkan.
     */
    #[DataProvider('emptyKeywords')]
    public function test_a_keyword_with_no_words_yields_nothing(string $keyword): void
    {
        $this->assertNull($this->terms()->forQuery($keyword));
    }

    /** @return array<string, array{0: string}> */
    public static function emptyKeywords(): array
    {
        return [
            'kosong' => [''],
            'spasi' => ['   '],
            'tanda baca' => ['???'],
            'simbol operator' => ['+++ --- ***'],
        ];
    }

    /**
     * Masukan mentah tidak boleh sampai ke BOOLEAN MODE: di sana
     * `+ - > < ( ) ~ * " @` adalah operator, dan kombinasi yang salah membuat
     * MySQL melempar galat sintaks alih-alih mengembalikan nol hasil.
     */
    public function test_operator_characters_can_never_reach_the_query(): void
    {
        $expression = $this->terms()->forQuery('cuci -ac +"rumah" (besar)');

        $this->assertNotNull($expression);
        $this->assertMatchesRegularExpression('/^[+\p{L}\p{N} *]+$/u', $expression);
        $this->assertStringNotContainsString('"', $expression);
        $this->assertStringNotContainsString('(', $expression);
        $this->assertStringNotContainsString('-', $expression);
    }

    public function test_the_number_of_query_terms_is_capped(): void
    {
        $expression = $this->terms()->forQuery(implode(' ', array_map(
            fn (int $i): string => 'kata'.$i,
            range(1, 40),
        )));

        $this->assertNotNull($expression);
        $this->assertCount(10, explode(' ', $expression));
    }

    public function test_the_indexed_text_is_bounded(): void
    {
        $huge = implode(' ', array_map(fn (int $i): string => 'kata'.$i, range(1, 2000)));

        $this->assertLessThanOrEqual(400, count($this->indexed($huge)));
    }

    public function test_repeated_words_are_stored_once(): void
    {
        $this->assertSame(['rumah'], $this->indexed('rumah rumah RUMAH rumah'));
    }

    public function test_several_parts_are_indexed_together(): void
    {
        $indexed = $this->indexed('Cuci AC', 'freon habis di kamar');

        $this->assertContains('cuci', $indexed);
        $this->assertContains('freon', $indexed);
    }
}
