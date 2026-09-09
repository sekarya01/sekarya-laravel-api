<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Task;
use App\Support\TaskSearch;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * Lihat catatan trait di ListTasksActionTest: indeks FULLTEXT InnoDB butuh
 * transaksi yang commit, jadi RefreshDatabase tidak bisa dipakai di sini.
 */
final class TaskSearchTest extends TestCase
{
    use DatabaseTruncation;

    private function task(string $title, string $description, ?float $lat = null, ?float $lng = null): Task
    {
        $this->seedReference();

        return Task::factory()->create([
            'poster_id' => $this->activeUser()->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'title' => $title,
            'description' => $description,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    /**
     * Kata kunci HARUS diselesaikan lewat indeks terbalik.
     *
     * Yang diperiksa adalah subquery indeksnya, bukan rencana kueri gabungan.
     * Rencana gabungan bergantung pada VOLUME: dengan tabel yang isinya
     * beberapa baris, MySQL wajar memilih memindai `tasks` karena memang lebih
     * murah — dan assertion yang menuntut sebaliknya akan menuntut rencana
     * yang salah. Yang tidak bergantung volume adalah ini: `MATCH ... AGAINST`
     * tidak punya jalan lain selain indeks FULLTEXT-nya.
     *
     * Rencana gabungan pada volume nyata (20.000 task) diperiksa terpisah;
     * catatannya ada di docs/API.md.
     */
    public function test_keyword_search_resolves_through_the_inverted_index(): void
    {
        $this->task('Cuci AC', 'freon habis');

        $query = Task::query()->select('tasks.*');
        app(TaskSearch::class)->applyKeyword($query, 'cuci');

        $sql = $query->toSql();

        $this->assertStringContainsString('task_search', $sql);
        $this->assertStringContainsString('MATCH(terms) AGAINST', $sql);
        // Tidak ada LIKE di jalur pencarian, dalam bentuk apa pun.
        $this->assertStringNotContainsStringIgnoringCase('like', $sql);

        $index = \DB::table('task_search')
            ->whereRaw('MATCH(terms) AGAINST (? IN BOOLEAN MODE)', ['+cuci*'])
            ->select('task_id');

        $plan = collect(\DB::select('EXPLAIN '.$index->toSql(), $index->getBindings()))
            ->map(fn ($row) => implode(' ', (array) $row))
            ->implode(' ');

        $this->assertStringContainsString('task_search_terms_fulltext', $plan);
    }

    /**
     * Pembuktian ujung ke ujung untuk kata pendek.
     *
     * `innodb_ft_min_token_size` bawaan MySQL adalah 3, jadi tanpa
     * penanganan khusus baris ini MUSTAHIL ditemukan — dan "cuci AC" adalah
     * pencarian yang wajar di aplikasi ini.
     */
    public function test_a_two_letter_word_is_findable(): void
    {
        $ac = $this->task('Cuci AC 2 unit', 'servis split');
        $this->task('Cuci karpet', 'ruang tamu');

        $this->assertSame([$ac->getKey()], $this->searchIds('ac'));
    }

    /** Sisi lain: kata pendek tidak ikut menangkap kata yang memuatnya. */
    public function test_a_two_letter_word_does_not_match_longer_words(): void
    {
        $this->task('Urai macet di depan gerbang', 'butuh pengatur lalu lintas');
        $this->task('Siapkan acara ulang tahun', 'dekorasi dan konsumsi');

        $this->assertSame([], $this->searchIds('ac'));
    }

    /** Imbuhan: yang diketik "bersih", yang tertulis "Membersihkan". */
    public function test_a_root_word_finds_the_affixed_title(): void
    {
        $target = $this->task('Membersihkan gudang', 'gudang dua lantai');
        $this->task('Antar paket', 'ke kantor pos');

        $this->assertSame([$target->getKey()], $this->searchIds('bersih'));
        $this->assertSame([$target->getKey()], $this->searchIds('bersihkan'));
        $this->assertSame([$target->getKey()], $this->searchIds('membersihkan'));
    }

    public function test_the_index_row_follows_a_renamed_task(): void
    {
        $task = $this->task('Cuci karpet', 'ruang tamu');

        $this->assertSame([$task->getKey()], $this->searchIds('karpet'));

        $task->forceFill(['title' => 'Potong rumput'])->save();

        // Indeks ikut berubah — kalau tidak, task ini akan selamanya ditemukan
        // lewat judul yang sudah tidak ada.
        $this->assertSame([], $this->searchIds('karpet'));
        $this->assertSame([$task->getKey()], $this->searchIds('rumput'));
    }

    /** @return list<int> */
    private function searchIds(string $keyword): array
    {
        $query = Task::query()->select('tasks.*');
        app(TaskSearch::class)->applyKeyword($query, $keyword);

        return $query->pluck('id')->map(intval(...))->all();
    }

    public function test_radius_search_uses_the_coordinate_index(): void
    {
        $query = Task::query()->select('tasks.*');
        app(TaskSearch::class)->applyRadius($query, -6.1754, 106.8272, 5.0);

        $plan = collect(\DB::select('EXPLAIN '.$query->toSql(), $query->getBindings()))
            ->map(fn ($row) => implode(' ', (array) $row))
            ->implode(' ');

        $this->assertStringContainsString('tasks_latitude_longitude_index', $plan);
    }

    /**
     * Regresi: `min()`/`max()` dua argumen sah di SQLite tapi hanya agregat di
     * MySQL, jadi bentuk itu ditolak galat 1064. Ekspresi harus memakai
     * LEAST/GREATEST.
     */
    public function test_the_distance_expression_is_valid_mysql(): void
    {
        $this->task('Dekat', 'x', -6.1754, 106.8272);

        $query = Task::query()->select('tasks.*');
        app(TaskSearch::class)->applyRadius($query, -6.1754, 106.8272, 5.0);

        // Tidak boleh melempar QueryException.
        $rows = $query->get();

        $this->assertCount(1, $rows);
        $this->assertNotNull($rows->first()->distance_km);
    }

    /**
     * Regresi: float yang diikat PDO harus dibandingkan sebagai angka.
     * Kalau tidak, `jarak <= ?` bernilai benar untuk semua baris.
     */
    public function test_the_radius_actually_excludes_far_rows(): void
    {
        $this->task('Jakarta', 'x', -6.1754, 106.8272);
        $this->task('Bandung', 'x', -6.9175, 107.6191);

        $query = Task::query()->select('tasks.*');
        app(TaskSearch::class)->applyRadius($query, -6.1754, 106.8272, 100.0);

        $this->assertSame(['Jakarta'], $query->pluck('title')->all());
    }

    public function test_punctuation_only_keyword_forces_zero_rows(): void
    {
        $this->task('Ada isinya', 'x');

        $query = Task::query()->select('tasks.*');
        app(TaskSearch::class)->applyKeyword($query, '!!! ???');

        $this->assertSame(0, $query->count());
    }

    public function test_keyword_terms_are_capped(): void
    {
        $this->task('Satu dua tiga empat lima enam tujuh delapan sembilan sepuluh', 'x');

        $query = Task::query()->select('tasks.*');
        // 12 kata: hanya 10 pertama yang dipakai, sisanya diabaikan.
        app(TaskSearch::class)->applyKeyword($query, 'satu dua tiga empat lima enam tujuh delapan sembilan sepuluh sebelas duabelas');

        $this->assertSame(1, $query->count());
    }

    /** Operator BOOLEAN MODE pada masukan mentah tidak boleh memicu galat. */
    public function test_operator_characters_in_input_are_neutralised(): void
    {
        $this->task('Cuci AC', 'x');

        foreach (['+cuci -ac', 'cuci*', '"cuci"', 'cuci~ac', '(cuci)', 'cuci @ ac'] as $raw) {
            $query = Task::query()->select('tasks.*');
            app(TaskSearch::class)->applyKeyword($query, $raw);

            // Yang penting: tidak melempar galat sintaks.
            $this->assertIsInt($query->count(), "masukan {$raw} memicu galat");
        }
    }
}
