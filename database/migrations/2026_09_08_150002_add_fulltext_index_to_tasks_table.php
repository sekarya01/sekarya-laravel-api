<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks FULLTEXT untuk pencarian kata kunci task.
 *
 * `LIKE '%kata%'` tidak bisa memakai indeks apa pun — biayanya memindai
 * seluruh tabel dan tumbuh linear terhadap jumlah task. FULLTEXT InnoDB
 * membangun indeks terbalik, jadi pencarian tetap cepat berapa pun datanya.
 *
 * Satu indeks pada dua kolom sekaligus, bukan dua indeks terpisah: MySQL
 * mensyaratkan daftar kolom pada MATCH() sama persis dengan daftar kolom
 * indeksnya. `MATCH(title, description)` hanya memakai indeks kalau ada
 * indeks FULLTEXT gabungan tepat atas (title, description).
 *
 * Catatan operasional yang perlu diketahui:
 *  - `innodb_ft_min_token_size` bawaan MySQL adalah 3, jadi kata dua huruf
 *    seperti "AC" TIDAK terindeks. Turunkan ke 1 di konfigurasi server bila
 *    kata pendek harus bisa dicari — perlu restart server lalu indeks
 *    dibangun ulang.
 *  - InnoDB punya daftar stopword bawaan (bahasa Inggris). Kata yang masuk
 *    daftar itu diabaikan saat mengindeks maupun mencari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->fullText(['title', 'description'], 'tasks_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropFullText('tasks_fulltext');
        });
    }
};
