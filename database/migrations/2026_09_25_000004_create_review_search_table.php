<?php

declare(strict_types=1);

use App\Support\SearchTerms;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks pencarian komentar ulasan (`GET users/{user}/reviews?q=`) — U7.
 *
 * Pola yang sama persis dengan `task_search`, dan karena alasan yang sama:
 *
 *  - Tanpa `LIKE`. Proyek ini tidak memakai `LIKE` di `app/`; FULLTEXT adalah
 *    indeks terbalik yang biayanya sebanding jumlah kecocokan.
 *  - Teks yang diindeks sudah DINORMALISASI oleh `App\Support\SearchTerms`
 *    (kata pendek bersentinel, akar kata ikut disimpan), kelas yang sama
 *    dengan sisi kueri. FULLTEXT langsung di `reviews.comment` akan membuat
 *    kata kunci bersentinel ("zqac") tidak pernah bertemu teks mentahnya.
 *  - Tabel terpisah, bukan kolom di `reviews`: daftar ulasan memakai
 *    `reviews.*`, dan teks olahan tidak pernah ditampilkan.
 *
 * Barisnya dijaga hook `saved` di model Review. Ulasan tanpa komentar tidak
 * punya baris — tidak ada yang bisa dicari darinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_search', function (Blueprint $table): void {
            // Satu baris per ulasan; review_id sekaligus primary key.
            $table->foreignId('review_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('terms');
        });

        $this->backfill();

        // Sesudah pengisian: membangun indeks terbalik sekali di akhir lebih
        // murah daripada memperbaruinya per baris.
        Schema::table('review_search', function (Blueprint $table): void {
            $table->fullText('terms', 'review_search_terms_fulltext');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_search');
    }

    /**
     * Isi dari ulasan yang sudah ada, lewat SearchTerms yang sama dengan kode
     * aplikasi — bukan aturan yang disalin ke SQL. Baris yang diisi migrasi
     * dan yang ditulis aplikasi harus melewati normalisasi yang identik.
     */
    private function backfill(): void
    {
        $terms = new SearchTerms;

        DB::table('reviews')
            ->select(['id', 'comment'])
            ->whereNotNull('comment')
            ->orderBy('id')
            ->chunk(500, function ($reviews) use ($terms): void {
                $rows = $reviews
                    ->map(fn ($review): array => [
                        'review_id' => $review->id,
                        'terms' => $terms->forIndex((string) $review->comment),
                    ])
                    ->filter(fn (array $row): bool => $row['terms'] !== '')
                    ->values()
                    ->all();

                if ($rows !== []) {
                    DB::table('review_search')->insert($rows);
                }
            });
    }
};
