<?php

declare(strict_types=1);

use App\Support\SearchTerms;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks pencarian nama pekerjaan, di TABEL TERSENDIRI.
 *
 * Sebelumnya indeks FULLTEXT menempel langsung di `tasks(title, description)`.
 * Itu bekerja, tapi dua hal tidak bisa diperbaiki dari sana:
 *
 *  - Kata di bawah `innodb_ft_min_token_size` (bawaan 3) tidak pernah masuk
 *    indeks, jadi "AC" mustahil ditemukan.
 *  - "membersihkan" dan "bersih" adalah dua kata yang berbeda bagi indeks,
 *    padahal bagi penggunanya sama.
 *
 * Keduanya butuh teks yang sudah DIOLAH — kata pendek diberi sentinel, akar
 * kata ikut disimpan — dan teks olahan itu tidak boleh menimpa `title` yang
 * dibaca manusia. Jadi ia tinggal di kolom sendiri.
 *
 * Kenapa TABEL terpisah, bukan kolom tambahan di `tasks`:
 *
 *  - Feed memakai `select('tasks.*')`. Sebuah kolom teks besar di `tasks`
 *    akan ikut terbaca untuk setiap baris feed, padahal tidak pernah
 *    ditampilkan — biaya I/O yang dibayar setiap permintaan.
 *  - Bentuk penyaringannya sama persis dengan filter keahlian yang sudah ada:
 *    subquery non-terkorelasi yang menghasilkan daftar id, lalu `tasks`
 *    dicari lewat primary key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_search', function (Blueprint $table): void {
            // Satu baris per task; task_id sekaligus primary key.
            $table->foreignId('task_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('terms');
        });

        $this->backfill();

        // Indeks dibuat SESUDAH pengisian: membangun indeks terbalik sekali di
        // akhir jauh lebih murah daripada memperbaruinya per baris.
        Schema::table('task_search', function (Blueprint $table): void {
            $table->fullText('terms', 'task_search_terms_fulltext');
        });

        // Indeks lama tidak lagi dipakai siapa pun. Membiarkannya berarti
        // membayar pemeliharaan indeks terbalik kedua pada setiap tulis.
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropFullText('tasks_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->fullText(['title', 'description'], 'tasks_fulltext');
        });

        Schema::dropIfExists('task_search');
    }

    /**
     * Isi dari task yang sudah ada.
     *
     * Memakai SearchTerms yang sama dengan kode aplikasi — bukan menyalin
     * aturannya ke SQL. Baris yang diisi migrasi dan baris yang ditulis
     * aplikasi harus melewati normalisasi yang identik, kalau tidak sebagian
     * data lama tidak akan pernah ditemukan.
     */
    private function backfill(): void
    {
        $terms = new SearchTerms;

        DB::table('tasks')
            ->select(['id', 'title', 'description'])
            ->orderBy('id')
            ->chunk(500, function ($tasks) use ($terms): void {
                $rows = $tasks->map(fn ($task): array => [
                    'task_id' => $task->id,
                    'terms' => $terms->forIndex((string) $task->title, (string) $task->description),
                ])->all();

                if ($rows !== []) {
                    DB::table('task_search')->insert($rows);
                }
            });
    }
};
