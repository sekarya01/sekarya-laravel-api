<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\File;

/**
 * Bangun `database/schema/sekarya-install.sql` dari basis data saat ini.
 *
 * Ada sebagai perintah, bukan sebagai rangkaian argumen `mysqldump` di
 * dokumentasi, karena berkas ini dipakai orang yang tidak punya SSH untuk
 * memasang seluruh aplikasi — kegagalannya muncul di tempat yang paling sulit
 * didiagnosis, di mesin orang lain. Satu argumen yang salah ketik saat
 * membuatnya ulang cukup untuk membuat pemasangan berikutnya gagal.
 *
 * Tiga hal yang dijamin perintah ini, dan ketiganya pernah menjadi kegagalan
 * nyata saat impor lewat phpMyAdmin:
 *
 * 1. TABEL DIURUTKAN MENURUT KETERGANTUNGAN, bukan abjad. `mysqldump`
 *    mengurutkan alfabetis, sehingga `activities` dibuat lebih dulu daripada
 *    `tasks`, `users`, dan `payments` yang dirujuk foreign key-nya. Dump itu
 *    hanya selamat karena `FOREIGN_KEY_CHECKS=0` — dan mysqldump menaruhnya di
 *    dalam komentar bersyarat `/*!40014 ... *\/`, yang boleh dilewati oleh
 *    klien mana pun yang tidak mengurainya. phpMyAdmin melewatinya.
 *
 * 2. Pernyataan pengaturan sesi ditulis POLOS, bukan di dalam komentar
 *    bersyarat, jadi tidak ada klien yang bisa melewatkannya.
 *
 * 3. Tidak ada penghapusan tabel, `CREATE TABLE IF NOT EXISTS`, dan
 *    `INSERT IGNORE` — sebagian shared hosting menahan hak DROP, dan impor yang
 *    putus di tengah harus bisa diulang tanpa membersihkan apa pun dulu.
 */
final class BuildInstallSqlCommand extends Command
{
    protected $signature = 'sekarya:build-install-sql
        {--path=database/schema/sekarya-install.sql : Tujuan penulisan}';

    protected $description = 'Bangun berkas pemasangan basis data untuk shared hosting';

    /** Tabel yang datanya ikut. Sisanya struktur saja. */
    private const array SEEDED_TABLES = ['categories', 'skills', 'migrations'];

    public function handle(ConnectionInterface $db): int
    {
        $database = (string) $db->getDatabaseName();
        $tables = $this->tablesInDependencyOrder($db, $database);

        $this->components->info(sprintf('Membaca %d tabel dari `%s`', count($tables), $database));

        $sql = $this->header()
            .$this->sessionPrelude()
            ."-- ---------- STRUKTUR TABEL ----------\n"
            ."-- Urut menurut ketergantungan: setiap foreign key menunjuk tabel\n"
            ."-- yang sudah dibuat di atasnya.\n\n"
            .$this->structure($db, $tables)
            ."-- ---------- DATA ACUAN ----------\n"
            ."-- Kategori dan keahlian: aplikasi tidak berjalan tanpanya.\n"
            ."-- Riwayat migrasi: supaya `php artisan migrate` tahu semuanya sudah jalan.\n\n"
            .$this->referenceData($db)
            .$this->sessionEpilogue();

        $path = base_path((string) $this->option('path'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $sql);

        $this->components->info(sprintf(
            '%s ditulis — %d baris, %s',
            $this->option('path'),
            substr_count($sql, "\n"),
            $this->humanSize(strlen($sql)),
        ));

        $this->components->warn(
            'Belum selesai. Buktikan impornya sebelum dikirim ke mana pun:'
            ."\n  php artisan db:wipe --force"
            ."\n  mysql -u root ".$database.' < '.$this->option('path')
            ."\n  mysql -u root ".$database.' < '.$this->option('path').'   # kedua kali, harus tetap berhasil'
            ."\n  php artisan migrate --pretend --force                    # harus: Nothing to migrate",
        );

        return self::SUCCESS;
    }

    /**
     * Urutan pembuatan tabel: yang dirujuk lebih dulu.
     *
     * Urutan inilah yang membuat berkas ini bisa diimpor klien yang tidak
     * mematikan pemeriksaan foreign key. Bergantung pada `FOREIGN_KEY_CHECKS=0`
     * saja berarti bergantung pada klien membaca satu baris yang boleh
     * dilewatinya.
     *
     * @return list<string>
     */
    private function tablesInDependencyOrder(ConnectionInterface $db, string $database): array
    {
        /** @var list<string> $all */
        $all = collect($db->select(
            'SELECT table_name AS t FROM information_schema.tables
             WHERE table_schema = ? AND table_type = "BASE TABLE" ORDER BY table_name',
            [$database],
        ))->pluck('t')->map(strval(...))->all();

        $dependsOn = [];

        foreach ($all as $table) {
            $dependsOn[$table] = collect($db->select(
                'SELECT DISTINCT referenced_table_name AS r
                 FROM information_schema.key_column_usage
                 WHERE table_schema = ? AND table_name = ? AND referenced_table_name IS NOT NULL',
                [$database, $table],
            ))->pluck('r')->map(strval(...))
                // Foreign key ke diri sendiri tidak menunda apa pun.
                ->reject(fn (string $r): bool => $r === $table)
                ->all();
        }

        $ordered = [];
        $remaining = $all;

        while ($remaining !== []) {
            $ready = array_values(array_filter(
                $remaining,
                fn (string $t): bool => array_diff($dependsOn[$t], $ordered) === [],
            ));

            if ($ready === []) {
                // Lingkaran ketergantungan. Tidak ada urutan yang benar, jadi
                // sisanya ditulis apa adanya — `FOREIGN_KEY_CHECKS=0` di kepala
                // berkas yang menanganinya. Disebutkan supaya tidak senyap.
                $this->components->warn(
                    'Lingkaran foreign key pada: '.implode(', ', $remaining)
                    .' — urutannya tidak bisa dijamin, berkas bergantung pada FOREIGN_KEY_CHECKS=0.',
                );
                $ready = $remaining;
            }

            foreach ($ready as $table) {
                $ordered[] = $table;
            }

            $remaining = array_values(array_diff($remaining, $ready));
        }

        return $ordered;
    }

    /** @param list<string> $tables */
    private function structure(ConnectionInterface $db, array $tables): string
    {
        $out = '';

        foreach ($tables as $table) {
            $row = (array) $db->selectOne('SHOW CREATE TABLE `'.$table.'`');
            $create = (string) ($row['Create Table'] ?? '');

            // `IF NOT EXISTS` membuat impor ulang setelah gagal separuh jalan
            // berhasil, alih-alih berhenti di "table already exists".
            $create = preg_replace(
                '/^CREATE TABLE /',
                'CREATE TABLE IF NOT EXISTS ',
                $create,
            );

            // AUTO_INCREMENT sisa data lokal tidak relevan di pemasangan baru,
            // dan membuat diff berkas ini berubah tanpa alasan.
            $create = (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', (string) $create);

            // ROW_FORMAT=DYNAMIC ditulis eksplisit.
            //
            // Beberapa kolom string di skema Laravel adalah `varchar(255)` dan
            // ikut menjadi kunci indeks — pada utf8mb4 itu 1020 byte. Batas
            // panjang kunci InnoDB dengan format baris lama (COMPACT/REDUNDANT)
            // adalah 767 byte, sehingga `CREATE TABLE` ditolak:
            //
            //     #1071 - Specified key was too long; max key length is 767 bytes
            //
            // DYNAMIC menaikkan batas itu ke 3072 byte. Di MySQL 8 ia sudah
            // bawaan dan baris ini tidak mengubah apa pun; di MySQL 5.7 dan
            // sebagian MariaDB, ia yang membuat impor berhasil. Ditulis
            // eksplisit karena bawaan server tidak bisa diandalkan, dan tidak
            // ada cara mengubahnya dari shared hosting.
            if (! str_contains((string) $create, 'ROW_FORMAT=')) {
                $create = (string) preg_replace(
                    '/\)\s*ENGINE=InnoDB/',
                    ') ENGINE=InnoDB ROW_FORMAT=DYNAMIC',
                    (string) $create,
                );
            }

            $out .= $create.";\n\n";
        }

        return $out;
    }

    private function referenceData(ConnectionInterface $db): string
    {
        $out = '';

        foreach (self::SEEDED_TABLES as $table) {
            $rows = $db->table($table)->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $columns = array_keys((array) $rows->first());
            $columnList = '`'.implode('`, `', $columns).'`';

            $out .= '-- '.$table.': '.$rows->count()." baris\n";

            foreach ($rows as $row) {
                $values = array_map(
                    fn (mixed $v): string => $this->quote($db, $v),
                    array_values((array) $row),
                );

                $out .= sprintf(
                    "INSERT IGNORE INTO `%s` (%s) VALUES (%s);\n",
                    $table,
                    $columnList,
                    implode(', ', $values),
                );
            }

            $out .= "\n";
        }

        return $out;
    }

    private function quote(ConnectionInterface $db, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => $db->getPdo()->quote((string) $value),
        };
    }

    /**
     * Pengaturan sesi, ditulis POLOS.
     *
     * mysqldump membungkusnya dalam `/*!40014 ... *\/` — komentar bersyarat
     * versi. MySQL mengeksekusinya, tapi klien yang mengurai berkas SQL sendiri
     * boleh melewatinya, dan phpMyAdmin melakukannya. Ditulis polos, tidak ada
     * yang bisa melewatkannya.
     */
    private function sessionPrelude(): string
    {
        return <<<'SQL'
            SET NAMES utf8mb4;
            SET FOREIGN_KEY_CHECKS = 0;
            SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
            SET TIME_ZONE = '+00:00';


            SQL;
    }

    private function sessionEpilogue(): string
    {
        return <<<'SQL'
            SET FOREIGN_KEY_CHECKS = 1;

            SQL;
    }

    private function header(): string
    {
        return <<<'SQL'
            -- =============================================================================
            --  Sekarya API — berkas pemasangan basis data
            -- =============================================================================
            --
            --  CARA IMPOR DI cPanel / phpMyAdmin
            --
            --    1. cPanel > MySQL Databases > buat basis data. Namanya otomatis diberi
            --       awalan nama akun, mis. `akunanda_sekarya`.
            --    2. Buat pengguna basis data, tambahkan ke basis data itu dengan
            --       ALL PRIVILEGES.
            --    3. Buka phpMyAdmin. KLIK NAMA BASIS DATANYA DI PANEL KIRI LEBIH DULU,
            --       sampai judul halaman berbunyi "Database: akunanda_sekarya".
            --       Baru kemudian tab Import > Choose File > Go.
            --
            --    Langkah 3 bukan formalitas. Berkas ini sengaja TIDAK memilih basis data
            --    sendiri, karena namanya berbeda di tiap akun hosting. Kalau Import
            --    dijalankan dari halaman utama phpMyAdmin, MySQL menjawab:
            --
            --        #1046 - No database selected
            --
            --  LEWAT SSH, kalau tersedia:
            --
            --    mysql -u PENGGUNA -p NAMA_DATABASE < sekarya-install.sql
            --
            --  ISINYA
            --    - Seluruh tabel beserta indeks, foreign key, dan indeks FULLTEXT
            --    - Kategori dan keahlian (data acuan; aplikasi tidak berjalan tanpanya)
            --    - Riwayat migrasi, supaya `php artisan migrate` tahu seluruh migrasi
            --      sudah dijalankan dan tidak mengulanginya di atas tabel yang sudah ada
            --
            --    TIDAK berisi pengguna, task, penawaran, pembayaran, atau data pribadi
            --    apa pun. Berkas ini aman disimpan di repositori publik.
            --
            --  SIFAT BERKAS INI
            --    - Tabel diurutkan menurut KETERGANTUNGAN, bukan abjad: setiap foreign
            --      key menunjuk tabel yang sudah dibuat di atasnya.
            --    - Tidak menghapus apa pun. CREATE TABLE IF NOT EXISTS dan INSERT IGNORE,
            --      jadi aman dijalankan ulang dan tidak menuntut hak DROP.
            --    - Tidak membuat dan tidak memilih basis data.
            --    - Butuh MySQL 8.0+ dengan InnoDB.
            --
            --  SESUDAH IMPOR
            --    Perubahan skema berikutnya memakai `php artisan migrate`, bukan berkas
            --    ini. Jangan pernah menjalankan `migrate:fresh` di produksi.
            --
            --  JANGAN SUNTING TANGAN. Dibuat oleh:
            --    php artisan sekarya:build-install-sql
            -- =============================================================================


            SQL;
    }

    private function humanSize(int $bytes): string
    {
        return $bytes < 1024 * 1024
            ? round($bytes / 1024).' KB'
            : round($bytes / 1024 / 1024, 1).' MB';
    }
}
