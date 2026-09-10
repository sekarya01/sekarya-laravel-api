# Tests

```bash
php artisan test                    # 740 test, 2.442 asersi
php artisan test --testsuite Unit   # tier cepat
composer coverage                   # ringkasan coverage di terminal
composer test-report                # semua laporan ke coverage/
```

## Laporan

`composer test-report` menulis empat berkas ke `coverage/` (di-gitignore):

| Berkas | Isi | Cara baca |
|---|---|---|
| `coverage/html/index.html` | coverage per berkas & per baris, klik-able | `open coverage/html/index.html` |
| `coverage/testdox.txt` | daftar semua test dalam bahasa manusia | `less coverage/testdox.txt` |
| `coverage/junit.xml` | hasil per test, format standar CI | dipakai runner CI |
| `coverage/clover.xml` | angka coverage mentah | skrip di bawah |

Angka ringkas dan daftar baris yang belum tercakup:

```bash
python3 - <<'EOF'
import xml.etree.ElementTree as ET
t = ET.parse('coverage/clover.xml')
m = t.getroot().find('project/metrics')
st, cst = int(m.get('statements')), int(m.get('coveredstatements'))
print(f"lines {cst}/{st} {cst/st*100:.2f}%")
for f in t.getroot().iter('file'):
    miss = [int(l.get('num')) for l in f.iter('line')
            if l.get('type') == 'stmt' and int(l.get('count')) == 0]
    if miss:
        print(f.get('name'), miss)
EOF
```

Terakhir dijalankan: **956 test, 3.501 assertion, 61 berkas** — diverifikasi pada
Laravel 11.55.1 / PHP 8.5.10.

**Coverage** (diukur pada Laravel 11.55.1 / PHP 8.5.10 dengan pcov):

| | |
|---|---|
| Baris — seluruh `app/` | **91,53%** (3912/4274) |
| Baris — **API saja** | **99,26%** (3912/3941) |
| Baris — dasbor web `/access/super_admin` | **0%** (0/333) |
| Method | 93,91% (664/707) |

**Angka `app/` diseret satu bagian yang memang belum punya test:** tujuh controller
dasbor web super_admin plus middleware-nya, 333 statement, nol tercakup. Itu bukan
cabang defensif melainkan kode yang belum diuji sama sekali — disebut di sini supaya
tidak menghilang di balik satu angka gabungan, dan supaya jelas apa yang harus
dikerjakan berikutnya.

Di luar itu, 29 statement tidak tercakup, dan hampir semuanya cabang defensif untuk
keadaan yang skema nyata tidak pernah capai. Yang paling perlu disebut:

| Berkas | Baris | Kenapa |
|---|---|---|
| `app/Actions/Auth/VerifyEmailAction.php` | 103 | Cek ulang setelah `lockForUpdate()`; butuh dua koneksi berbarengan |
| `app/Console/Commands/BuildInstallSqlCommand.php` | 141-145 | Peringatan lingkaran foreign key; skema ini tidak punya lingkaran |
| `app/Console/Commands/BuildInstallSqlCommand.php` | 215 | Cabang tabel acuan kosong (lihat catatan di bawah) |

Baris 215 tidak bisa dijangkau test dengan andal: suite ini mencampur
RefreshDatabase dan DatabaseTruncation, jadi `categories` bisa berisi baris
ter-commit dari kelas lain dan tidak pernah dijamin kosong. Yang dijaga adalah
invariannya — tidak ada `INSERT` tanpa kolom — bukan cabangnya.

`pcov` sudah terpasang tapi tidak dimuat lewat `php.ini`; script `composer coverage`
memuatnya sendiri lewat `-d extension=`. Jadi `php -m` tidak menampilkannya.

## Prasyarat sekali pasang

**1. Database test terpisah.** `RefreshDatabase` menghapus isi database, jadi jangan
diarahkan ke database dev:

```sql
CREATE DATABASE sekarya_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Sudah dirujuk `phpunit.xml` (`DB_DATABASE=sekarya_test`).

**2. Driver coverage.** Tidak dipasang global agar tidak membebani request biasa; PCOV
dimuat lewat flag saat diminta:

```bash
composer coverage
# setara:
# php -d extension=<path>/pcov.so -d pcov.enabled=1 vendor/bin/phpunit --coverage-text
```

Kalau `pcov.so` belum ada:

```bash
brew install pcre2
CPPFLAGS="-I/opt/homebrew/opt/pcre2/include" pecl install pcov
```

`CPPFLAGS` itu perlu karena PHP Homebrew tidak memasang header `pcre2.h`, dan tanpanya
build PCOV gagal.

## MySQL, bukan SQLite

`phpunit.xml` memakai MySQL dengan sengaja. Aplikasi ini memakai sintaks khusus MySQL:
`MATCH(...) AGAINST (... IN BOOLEAN MODE)` untuk pencarian, dan `LEAST()/GREATEST()` di
ekspresi jarak. Di SQLite test pencarian bukan hanya membuktikan lebih sedikit — ia
**gagal**, dan yang lolos tidak membuktikan apa pun soal produksi.

## Dua strategi database, dan mengapa

| Strategi | Dipakai di | Alasan |
|---|---|---|
| `RefreshDatabase` | hampir semua kelas | transaksi + rollback, cepat |
| `DatabaseTruncation` | `ListTasksActionTest`, `TaskSearchTest`, `FeedFilterTest` | **wajib** untuk FULLTEXT |

Indeks FULLTEXT InnoDB baru diperbarui setelah transaksi **COMMIT**. Di bawah
`RefreshDatabase`, baris yang dibuat di dalam test tidak akan pernah terlihat oleh
`MATCH ... AGAINST`, sehingga seluruh test pencarian mengembalikan nol baris dan tampak
seperti bug aplikasi — padahal aplikasinya benar.

> [!important] Konsekuensinya: **assertion tidak boleh mengandaikan tabelnya kosong.**
> Kelas truncation meninggalkan baris ter-commit. Lingkupi kueri ke baris yang test itu
> sendiri buat (`whereIn('id', …)`, `where('user_id', …)`) alih-alih menghitung seluruh
> tabel. Aturan ini sudah menjebak tiga test di sini.

## Mengganti identitas antar request

Pakai `$this->asUser($user)` dari `Tests\TestCase`. Helper itu memanggil
`forgetGuards()` — **wajib**, karena guard yang sudah meresolusi pengguna me-memoize
hasilnya pada instance aplikasi yang dipakai ulang seluruh request dalam satu test.
Tanpa flush itu, request kedua tetap dianggap pengguna pertama, dan **setiap** pemeriksaan
"orang lain harus 403" lulus palsu dengan 200.

## Struktur

```
tests/
├── TestCase.php                 helper bersama + catatan strategi database
├── Unit/                        Action, Support, Model, Enum, DTO  (tanpa HTTP)
│   ├── Actions/                 seluruh logika bisnis
│   ├── Support/                 TokenIssuer, TaskSearch, TaskStatusRecorder
│   ├── Models/                  scope, helper, relasi, enkripsi
│   ├── Enums/                   state machine (tanpa database)
│   └── Data/                    DTO & clamping
└── Feature/
    ├── Api/V1/                  34 endpoint lewat HTTP
    │   ├── AuthFlowTest         daftar → verifikasi → login → refresh → logout
    │   ├── SecurityTest         jenis token, rate limit, CORS, pengungkapan data
    │   ├── TaskLifecycleTest    task → lelang → deal → uang → activity → nilai
    │   ├── FeedFilterTest       kata kunci, jarak, keahlian, pagination
    │   ├── CatalogAndProfileTest
    │   └── OptionalFieldsTest
    └── Docs/                    rute dokumentasi hanya hidup di luar produksi
```

## Coverage

**99,94% baris** (1701/1702), **99,73% method**. Per kelompok: `Http`, `Models`, `Data`,
`Enums`, `Exceptions`, `Policies`, `Providers`, `Support`, `Notifications` semuanya 100%;
`Actions` 99,84%.

Satu baris sengaja dibiarkan: pemeriksaan ulang setelah `lockForUpdate()` di
`VerifyEmailAction::consume()`. Itu penjaga kondisi balapan — hanya bisa terpicu kalau
permintaan lain mengonsumsi kode yang sama di antara pemeriksaan pertama dan lock.
Menutupnya butuh dua koneksi database yang dijalankan bersamaan; menghapusnya berarti satu
kode bisa dipakai dua kali.
