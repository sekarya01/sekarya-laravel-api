<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Task;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pencarian kata kunci dan jarak untuk task.
 *
 * Dua keputusan yang menentukan kecepatannya:
 *
 * 1. KATA KUNCI lewat indeks FULLTEXT, bukan LIKE. `LIKE '%kata%'` tidak bisa
 *    memakai indeks apa pun sehingga selalu memindai seluruh tabel, dan
 *    biayanya tumbuh linear terhadap jumlah task. FULLTEXT adalah indeks
 *    terbalik — biayanya sebanding jumlah kecocokan, bukan jumlah baris.
 *
 * 2. JARAK lewat bounding box DULU, haversine sesudahnya. Menghitung
 *    haversine untuk setiap baris berarti memindai seluruh tabel karena hasil
 *    perhitungan tidak bisa diindeks. Bounding box memakai index
 *    (latitude, longitude), lalu haversine hanya dijalankan pada sisa yang
 *    sedikit — untuk membuang sudut-sudut kotak yang di luar radius.
 */
final class TaskSearch
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly SearchTerms $terms,
    ) {}

    /** Derajat lintang per kilometer. */
    private const float KM_PER_LAT_DEGREE = 111.045;

    private const float EARTH_RADIUS_KM = 6371.0;

    /**
     * Pencarian nama pekerjaan lewat indeks terbalik `task_search`.
     *
     * Bentuknya subquery NON-terkorelasi — daftar id dibangun sekali dari
     * indeks FULLTEXT, lalu `tasks` dicari lewat primary key. Sama persis
     * dengan pola filter keahlian di bawah, dan karena alasan yang sama:
     * `EXISTS` terkorelasi akan dievaluasi ulang untuk setiap baris tasks.
     *
     * Teks yang dicocokkan adalah teks yang SUDAH dinormalisasi, bukan judul
     * mentah — itulah yang membuat "AC" bisa ditemukan dan "bersih" bertemu
     * "membersihkan". Aturannya ada di SearchTerms, dan sisi indeks memakai
     * kelas yang sama.
     *
     * @param  Builder<Task>  $query
     */
    public function applyKeyword(Builder $query, string $keyword): void
    {
        $expression = $this->terms->forQuery($keyword);

        if ($expression === null) {
            // Kata kunci yang seluruhnya tanda baca: jangan kembalikan semuanya
            // seolah tidak ada filter — itu menyesatkan. Kembalikan nol baris.
            $query->whereRaw('1 = 0');

            return;
        }

        $taskIds = $this->db->table('task_search')
            ->whereRaw('MATCH(terms) AGAINST (? IN BOOLEAN MODE)', [$expression])
            ->select('task_id');

        $query->whereIn('tasks.id', $taskIds);
    }

    /**
     * Filter radius. Menambahkan kolom `distance_km` supaya jaraknya bisa
     * ditampilkan tanpa dihitung ulang di klien.
     *
     * @param  Builder<Task>  $query
     */
    public function applyRadius(
        Builder $query,
        float $latitude,
        float $longitude,
        float $radiusKm,
    ): void {
        $latDelta = $radiusKm / self::KM_PER_LAT_DEGREE;
        // Sepanjang garis bujur, jarak per derajat menyusut mendekati kutub.
        $cos = max(cos(deg2rad($latitude)), 0.000001);
        $lngDelta = $radiusKm / (self::KM_PER_LAT_DEGREE * $cos);

        $query
            ->whereNotNull('tasks.latitude')
            ->whereNotNull('tasks.longitude')
            // Prafilter yang memakai index (latitude, longitude).
            // Prafilter ini yang memakai index (latitude, longitude).
            ->whereBetween('tasks.latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('tasks.longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
            // Jarak ikut dipilih agar bisa ditampilkan tanpa dihitung ulang di klien.
            ->selectRaw(
                $this->haversineSql().' as distance_km',
                [$latitude, $longitude, $latitude],
            )
            // WHERE, bukan HAVING. Alias kolom belum tersedia pada tahap WHERE
            // sehingga ekspresinya diulang; biayanya tetap kecil karena hanya
            // dijalankan pada baris yang sudah lolos bounding box.
            //
            // HAVING juga bukan pilihan: SQL standar hanya mengizinkannya pada
            // kueri agregat, dan SQLite menolaknya secara eksplisit.
            ->whereRaw(
                $this->haversineSql().' <= ?',
                [$latitude, $longitude, $latitude, $radiusKm],
            );
    }

    /**
     * Filter task berdasarkan slug keahlian.
     *
     * Memakai subquery NON-terkorelasi, bukan whereHas. whereHas menghasilkan
     * `EXISTS (...)` yang dievaluasi ulang untuk setiap baris tasks — rencana
     * kuerinya jadi `SCAN tasks`. Bentuk `id IN (subquery)` dibangun sekali
     * dari indeks pivot, lalu tasks dicari lewat primary key.
     *
     * @param  Builder<Task>  $query
     * @param  list<string>  $slugs
     */
    public function applySkillSlugs(Builder $query, array $slugs): void
    {
        $taskIds = $this->db->table('skill_task')
            ->join('skills', 'skills.id', '=', 'skill_task.skill_id')
            ->whereIn('skills.slug', $slugs)
            ->select('skill_task.task_id');

        $query->whereIn('tasks.id', $taskIds);
    }

    /**
     * Filter task yang membutuhkan salah satu keahlian yang DIMILIKI user.
     *
     * Satu subquery yang menggabungkan dua pivot berindeks — bukan memuat
     * daftar keahlian user lebih dulu lalu mengirimkannya kembali sebagai
     * daftar nilai.
     *
     * @param  Builder<Task>  $query
     */
    public function applyMatchingSkills(Builder $query, int $userId): void
    {
        $taskIds = $this->db->table('skill_task')
            ->join('skill_user', 'skill_user.skill_id', '=', 'skill_task.skill_id')
            ->where('skill_user.user_id', $userId)
            ->select('skill_task.task_id');

        $query->whereIn('tasks.id', $taskIds);
    }

    /**
     * Ekspresi haversine. Ditulis satu kali di sini lalu dipakai baik untuk
     * memilih kolom `distance_km` maupun untuk membatasi radius, sehingga
     * keduanya tidak bisa melenceng satu dari yang lain.
     *
     * Menerima tiga binding berurutan: lat, lng, lat.
     */
    private function haversineSql(): string
    {
        // Tanpa CAST. MySQL mengoersi string jadi angka dalam konteks
        // perbandingan numerik, jadi float yang diikat PDO sebagai string
        // tetap dibandingkan sebagai angka.
        //
        // Ini BERBEDA dari SQLite, yang membandingkan antar kelas penyimpanan
        // sehingga angka selalu < teks dan `jarak <= ?` menjadi TRUE untuk
        // semua baris. Kalau suatu saat koneksinya dikembalikan ke SQLite,
        // ketiga parameter di bawah dan pembanding radius WAJIB dibungkus
        // CAST(? AS REAL).
        // LEAST/GREATEST mengapit argumen acos ke [-1, 1]: pembulatan floating
        // point bisa menghasilkan 1.0000000002 sehingga acos mengembalikan NULL.
        //
        // LEAST/GREATEST, BUKAN min/max — di MySQL `min()` dan `max()` hanya
        // fungsi agregat dan tidak menerima dua argumen, jadi bentuk
        // `min(1.0, max(-1.0, x))` yang sah di SQLite ditolak galat 1064.
        return sprintf(
            '%s * acos(LEAST(1.0, GREATEST(-1.0, '
            .'cos(radians(?)) * cos(radians(tasks.latitude)) '
            .'* cos(radians(tasks.longitude) - radians(?)) '
            .'+ sin(radians(?)) * sin(radians(tasks.latitude))'
            .')))',
            self::EARTH_RADIUS_KM,
        );
    }
}
