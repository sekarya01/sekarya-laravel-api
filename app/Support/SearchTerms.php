<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalisasi kata untuk pencarian nama pekerjaan.
 *
 * Dipakai di DUA tempat dan itu inti dari kelas ini: saat baris diindeks, dan
 * saat kata kunci diterjemahkan jadi kueri. Kalau kedua sisi memakai aturan
 * yang berbeda, pencarian gagal tanpa satu pun galat — hasilnya cuma kosong,
 * dan tidak ada yang tahu kenapa. Karena itu keduanya berangkat dari satu
 * pemecah kata yang sama di kelas ini.
 *
 * Dua kelemahan FULLTEXT bawaan yang ditutup di sini, keduanya TANPA `LIKE`
 * dan tanpa mengubah konfigurasi server:
 *
 * 1. KATA PENDEK. `innodb_ft_min_token_size` bawaan MySQL adalah 3, jadi "AC"
 *    tidak pernah masuk indeks dan tidak akan pernah ditemukan — padahal
 *    "cuci AC" adalah pencarian yang wajar di aplikasi ini. Kata di bawah
 *    ambang itu disimpan dengan awalan sentinel (`ac` -> `zqac`) sehingga
 *    panjangnya cukup untuk diindeks, dan kata kunci pendek diubah dengan
 *    aturan yang sama saat mencari. Hasilnya kecocokan PERSIS — berbeda dari
 *    parser ngram, yang membuat "ac" ikut cocok dengan "macet" dan "acara".
 *
 * 2. IMBUHAN. Pengguna mengetik "bersih", judulnya berbunyi "membersihkan".
 *    Akar katanya ikut disimpan di indeks, jadi keduanya bertemu.
 *
 * Pemenggalan imbuhan HANYA dilakukan saat mengindeks, tidak saat mencari.
 * Itu keputusan yang membuat kesalahan penggal tidak berbahaya: akar yang
 * salah cuma menjadi kata tambahan yang tidak pernah dicari siapa pun. Kalau
 * penggalan juga dipakai di sisi kueri, satu akar yang salah langsung berubah
 * jadi hasil pencarian yang salah di depan pengguna.
 */
final class SearchTerms
{
    /** Ambang `innodb_ft_min_token_size` bawaan MySQL. */
    public const int MIN_TOKEN_LENGTH = 3;

    /**
     * Awalan untuk kata di bawah ambang.
     *
     * `zq` dipilih karena bukan pasangan huruf yang muncul di awal kata
     * Indonesia maupun Inggris, jadi ia tidak bisa bertabrakan dengan kata
     * sungguhan yang kebetulan ada di judul.
     */
    private const string SHORT_TOKEN_PREFIX = 'zq';

    /** Batas kata yang disimpan per baris, supaya kolom indeks tidak liar. */
    private const int MAX_INDEXED_TOKENS = 400;

    /** Batas kata per pencarian: tiap kata jadi satu term di BOOLEAN MODE. */
    private const int MAX_QUERY_TERMS = 10;

    /**
     * Akhiran yang dipenggal. `-i` sengaja TIDAK ada di sini: memenggalnya
     * mengubah "pergi" jadi "perg" dan "kali" jadi "kal" jauh lebih sering
     * daripada ia menemukan akar yang benar.
     *
     * @var list<string>
     */
    private const array SUFFIXES = ['nya', 'kan', 'lah', 'kah', 'an'];

    /**
     * Awalan, beserta huruf yang dikembalikan karena luluh oleh nasal.
     *
     * "menyapu" adalah `meny` + `sapu` — huruf `s` hilang saat diimbuhi, jadi
     * ia harus dikembalikan. Sebagian awalan punya dua kemungkinan
     * ("mem" + "otong" -> "potong", tapi "mem" + "bersih" tetap "bersih"),
     * dan KEDUANYA disimpan. Yang keliru tidak merugikan: ia hanya kata
     * tambahan di indeks yang tak seorang pun mencarinya.
     *
     * Diurutkan dari yang terpanjang supaya "meny" diuji sebelum "me".
     *
     * @var list<array{0: string, 1: list<string>}>
     */
    private const array PREFIX_RULES = [
        ['meny', ['s']],
        ['peny', ['s']],
        ['meng', ['', 'k']],
        ['peng', ['', 'k']],
        ['mem', ['', 'p']],
        ['pem', ['', 'p']],
        ['men', ['', 't']],
        ['pen', ['', 't']],
        ['ber', ['']],
        ['ter', ['']],
        ['per', ['']],
        ['me', ['']],
        ['pe', ['']],
        ['di', ['']],
        ['ke', ['']],
        ['se', ['']],
    ];

    /** Akar sependek ini hampir selalu hasil penggalan yang salah. */
    private const int MIN_STEM_LENGTH = 4;

    /**
     * Teks yang disimpan di kolom indeks.
     *
     * Menerima beberapa bagian (judul, deskripsi) supaya pemanggilnya tidak
     * perlu menggabungkan sendiri dan salah menaruh pemisah.
     */
    public function forIndex(string ...$parts): string
    {
        $out = [];

        foreach ($this->tokenize(implode(' ', $parts)) as $token) {
            foreach ($this->indexFormsOf($token) as $form) {
                $out[$form] = true;

                if (count($out) >= self::MAX_INDEXED_TOKENS) {
                    break 2;
                }
            }
        }

        return implode(' ', array_keys($out));
    }

    /**
     * Kata kunci -> ekspresi BOOLEAN MODE.
     *
     * `null` berarti masukan tidak menyisakan satu kata pun (mis. seluruhnya
     * tanda baca). Pemanggil harus memperlakukannya sebagai NOL hasil, bukan
     * sebagai "tidak ada filter" — mengembalikan semua task untuk pencarian
     * "???" menyesatkan.
     */
    public function forQuery(string $keyword): ?string
    {
        $tokens = array_slice($this->tokenize($keyword), 0, self::MAX_QUERY_TERMS);

        if ($tokens === []) {
            return null;
        }

        $last = array_key_last($tokens);
        $parts = [];

        foreach ($tokens as $i => $token) {
            if ($this->isShort($token)) {
                // Kata pendek dicocokkan PERSIS: awalannya sudah membuat
                // panjangnya cukup, dan `*` di sini justru akan membuat "ac"
                // ikut menangkap "acara" lewat sentinel yang sama.
                $parts[] = '+'.self::SHORT_TOKEN_PREFIX.$token;

                continue;
            }

            // Kata TERAKHIR diberi awalan `*` supaya terasa seperti mengetik:
            // "bersih rum" sudah menemukan "bersih rumah" sebelum selesai
            // diketik. Kata sebelumnya tidak — di situ penggunanya sudah
            // selesai mengetik, dan `*` hanya menambah hasil yang melenceng.
            $parts[] = $i === $last ? '+'.$token.'*' : '+'.$token;
        }

        return implode(' ', $parts);
    }

    /**
     * Bentuk-bentuk yang disimpan untuk satu kata.
     *
     * @return list<string>
     */
    private function indexFormsOf(string $token): array
    {
        if ($this->isShort($token)) {
            // Bentuk aslinya ikut disimpan meski tidak terindeks: ia tidak
            // memakan biaya berarti, dan membuat isi kolom tetap terbaca saat
            // seseorang memeriksanya.
            return [$token, self::SHORT_TOKEN_PREFIX.$token];
        }

        return [$token, ...$this->stems($token)];
    }

    private function isShort(string $token): bool
    {
        return mb_strlen($token) < self::MIN_TOKEN_LENGTH;
    }

    /**
     * Akar kata yang mungkin. Bisa kosong, bisa lebih dari satu.
     *
     * @return list<string>
     */
    private function stems(string $token): array
    {
        $base = $this->stripSuffix($token);

        $candidates = $base === $token ? [] : [$base];

        // Awalan dikupas dari KEDUA bentuk: yang masih berakhiran dan yang
        // sudah dipotong akhirannya.
        //
        // Tanpa yang pertama, "Membersihkan" hanya tersimpan sebagai
        // "membersihkan" dan "bersih" — dan pengguna yang mengetik
        // "bersihkan" tidak menemukan apa pun, padahal itu bentuk yang sangat
        // wajar diketik. Bentuk antaranya harus ikut.
        foreach (array_unique([$token, $base]) as $form) {
            $candidates = [...$candidates, ...$this->stripPrefix($form)];
        }

        return array_values(array_unique(array_filter(
            $candidates,
            fn (string $c): bool => $c !== $token && mb_strlen($c) >= self::MIN_STEM_LENGTH,
        )));
    }

    /**
     * Kupas SATU awalan. Bisa menghasilkan lebih dari satu kemungkinan karena
     * huruf yang luluh oleh nasal harus ditebak.
     *
     * Mengupas berlapis ("mempertanggungjawabkan") butuh kamus akar kata untuk
     * tahu kapan berhenti; tanpa itu ia mengupas terus sampai jadi potongan
     * yang tidak berarti.
     *
     * @return list<string>
     */
    private function stripPrefix(string $word): array
    {
        foreach (self::PREFIX_RULES as [$prefix, $replacements]) {
            if (! str_starts_with($word, $prefix)) {
                continue;
            }

            $rest = substr($word, strlen($prefix));

            return array_map(
                static fn (string $replacement): string => $replacement.$rest,
                $replacements,
            );
        }

        return [];
    }

    private function stripSuffix(string $token): string
    {
        foreach (self::SUFFIXES as $suffix) {
            if (! str_ends_with($token, $suffix)) {
                continue;
            }

            $base = substr($token, 0, -strlen($suffix));

            if (mb_strlen($base) >= self::MIN_STEM_LENGTH) {
                return $base;
            }
        }

        return $token;
    }

    /**
     * Pemecah kata — SATU-SATUNYA, dipakai sisi indeks maupun sisi kueri.
     *
     * Masukan mentah tidak boleh sampai ke BOOLEAN MODE: di sana
     * `+ - > < ( ) ~ * " @` adalah operator, dan kombinasi yang salah membuat
     * MySQL melempar galat sintaks alih-alih mengembalikan nol hasil.
     * Memecahnya jadi huruf dan angka saja membuat masalah itu hilang di
     * sumbernya, bukan ditambal dengan daftar karakter yang harus di-escape.
     *
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $tokens = preg_split(
            '/[^\p{L}\p{N}]+/u',
            mb_strtolower(trim($text)),
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        return $tokens === false ? [] : array_values($tokens);
    }
}
