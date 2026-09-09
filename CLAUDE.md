# Sekarya API

Laravel 13 / PHP 8.5 REST API. **Action-Based Architecture** (Clean Architecture / DDD Lite).

The full blueprint lives in the `laravel-action-api` skill — invoke it before writing code here.
This file records only what is specific to *this* project.

## Request lifecycle

```
FormRequest (shape validation) -> DTO (app/Data) -> Action (app/Actions)
  -> Model (app/Models) -> API Resource (app/Http/Resources) -> JSON
```

Business logic lives **only** in Action classes. Never in Controllers, Models, Requests or Resources.

## Project decisions

| Decision | Value | Note |
|---|---|---|
| Database | **MySQL 8+ / InnoDB** | Switched from SQLite on 2026-09-08 by explicit instruction. |
| Auth | Sanctum (`auth:sanctum`) | via `php artisan install:api`. |
| Tests | PHPUnit 12 | Pest is *not* installed. |
| Money | integer, smallest unit | `transactions.deal_price` is `unsignedBigInteger`. |
| Pagination | cursor only | `paginate()` is banned. See below. |
| API prefix | `/api/v1`, routes named `v1.*` | one route line per invokable controller. |

## MySQL notes you must not forget

- **`lockForUpdate()` works here.** InnoDB translates it to `SELECT ... FOR UPDATE`,
  so the check-then-act in `AcceptBidAction` and `HoldPaymentAction` is genuinely
  serialised. The DB-level unique indexes (`tasks.accepted_bid_id`,
  `activities.payment_id`) are kept as the last line of defence anyway — a lock only
  holds inside a transaction, and writes from other paths may not take one.
- **Keyword search uses a FULLTEXT index**, never `LIKE`. `MATCH()` must name exactly
  the columns of the composite index `tasks_fulltext (title, description)` — a
  mismatched column list silently skips the index.
- **`innodb_ft_min_token_size` defaults to 3**, so two-letter words such as "AC" are
  not indexed and cannot be found. Lower it to 1 in the server config if short words
  must be searchable; that needs a server restart plus an index rebuild. InnoDB also
  applies a built-in (English) stopword list.
- **No CAST needed around bound floats.** MySQL coerces strings to numbers in numeric
  comparisons. This differs from SQLite, which compares across storage classes so
  a number is always less than text — if the connection is ever moved back to SQLite,
  every bound float in `TaskSearch::haversineSql()` and the radius comparison must be
  wrapped in `CAST(? AS REAL)` or the radius filter silently matches everything.
- **`engine` is pinned to InnoDB** in `config/database.php`. Foreign keys,
  transactions, row locks and FULLTEXT all require it; leaving it null defers the
  choice to the server default, which varies between environments.
- Distance filtering is a bounding box on the `(latitude, longitude)` index first,
  haversine second. Never haversine alone — a computed expression cannot be indexed,
  so it would scan the whole table.

## Deploy

Panduan shared hosting: `docs/DEPLOYMENT.md`. Dua artefak yang harus tetap seiring, dan
keduanya dijaga test:

- **`database/schema/sekarya-install.sql`** — pemasangan sekali jalan untuk hosting tanpa
  SSH. Dibuat oleh `php artisan sekarya:build-install-sql`, **jangan disunting tangan** dan
  jangan dibuat dengan `mysqldump`: mysqldump mengurutkan tabel secara alfabetis, sehingga
  `activities` dibuat sebelum `tasks`/`users`/`payments` yang dirujuknya. Dump itu hanya
  selamat karena `FOREIGN_KEY_CHECKS=0` di dalam komentar bersyarat `/*!40014 ... */` —
  dan phpMyAdmin melewati komentar itu, sehingga impornya berhenti di `CREATE TABLE`
  pertama tanpa keterangan. Perintahnya mengurutkan menurut ketergantungan.
  Migrasi baru **wajib** ikut ke sini; `tests/Feature/Deployment/InstallSchemaTest.php`
  menggagalkan suite kalau tidak, dan menolak data selain kategori, keahlian, dan migrasi —
  berkas ini ada di repo publik.
- **`.env.production.example`** — template produksi, tiap nilai berkomentar.

Yang mudah terlewat: **cache rute harus dibuat DI SERVER.** Rute `/docs` didaftarkan hanya
saat `APP_ENV` bukan production; cache yang dibuat di laptop akan membawa spesifikasi API
lengkap ke produksi.

## Feed & pencarian nama

Filter feed: `q` (nama), `lat`/`lng`/`radius_km` (jarak), `posted_within_hours` (waktu),
`category_id`, `skills`/`match_my_skills`. **Tidak satu pun memakai `LIKE`** — nol
kemunculan `LIKE` di `app/`, dan itu harus tetap begitu.

Pencarian nama lewat tabel **`task_search`**, bukan indeks di `tasks` langsung:

- **Teks yang diindeks sudah dinormalisasi**, bukan judul mentah. `App\Support\SearchTerms`
  adalah SSOT-nya dan dipakai di DUA sisi — saat mengindeks dan saat mencari. Kalau kedua
  sisi memakai aturan berbeda, pencarian gagal **tanpa galat**: hasilnya cuma kosong.
- **Tabel terpisah, bukan kolom di `tasks`.** Feed memakai `select('tasks.*')`; kolom teks
  besar di `tasks` akan ikut terbaca setiap permintaan padahal tak pernah ditampilkan.
- **Barisnya dijaga hook `saved` di model `Task`**, bukan di Action. Ada tiga jalur yang
  menulis judul (buat, sunting, factory), dan indeks yang meleset tidak menimbulkan galat —
  task itu sekadar tidak pernah muncul di pencarian.
- **Kata pendek** (`ac`) disimpan bersentinel (`zqac`) supaya melewati
  `innodb_ft_min_token_size` bawaan 3. Mesin dev ini menyetelnya ke 1, jadi test pencarian
  "ac" di sini **lulus palsu** — yang diuji adalah invariannya: sisi kueri tidak pernah
  mengeluarkan kata di bawah 3 huruf.
- **Imbuhan dipenggal HANYA saat mengindeks**, tidak saat mencari. Akar salah penggal di
  indeks cuma jadi kata yang tak pernah dicari; di sisi kueri ia langsung jadi hasil salah.
- Rencana kueri diperiksa pada **20.000 task**: `Full-text index search on task_search`
  lebih dulu, lalu lookup primary key. Dengan tabel kecil MySQL wajar memilih memindai
  `tasks` — jangan menilai rencana kueri dari tabel yang isinya beberapa baris.

## Satu task, banyak pekerja

`tasks.workers_needed` (default 1) adalah berapa orang yang **diterima**, bukan batas
pelamar. **Lelangnya tetap terbuka**: siapa pun boleh menawar, dan pemberi kerja memilih
pemenangnya berdasarkan harga penawaran. Mengisi slot terakhir menutup lelang dan menolak
pelamar yang masih menunggu.

> Jangan menambahkan kuota pelamar di `PlaceBidAction`. Itu pernah dicoba dan salah:
> membatasi pelamar ke jumlah slot menghapus perbandingan tawaran — pada task satu orang
> ia menghapus lelangnya sama sekali, dan pelamar pertama jadi satu-satunya kandidat.

Yang tidak boleh "dirapikan":

- **Tidak ada `tasks.worker_id` / `accepted_bid_id`.** Satu kolom tidak bisa menyimpan
  tiga puluh nilai, dan menyimpannya "untuk yang satu orang" berarti dua sumber kebenaran
  untuk pertanyaan yang sama. Sumbernya baris `bids` berstatus `accepted`.
- **`tasks.agreed_amount` adalah TOTAL seluruh pekerja**, bukan harga satu orang. Karena
  itu harga referensi kategori dihitung dari `bids.amount` yang diterima — memakai total
  akan menggeser median kategori sebesar jumlah pekerjanya.
- **Satu `payment` per task, banyak `activities`.** Pemberi kerja transfer sekali;
  pembagiannya di `activities.agreed_amount` yang disalin dari penawaran masing-masing.
  Penjaga "tidak ada activity tanpa dana ditahan" kini `unique (task_id, worker_id)`,
  bukan lagi `unique (payment_id)`.
- **Status task mengikuti AGREGAT, bukan pekerja tercepat.** `submitted` hanya kalau semua
  sudah menyerahkan; `completed` + dana dilepas hanya kalau semua disetujui. Melepas pada
  persetujuan pertama akan mengeluarkan seluruh tagihan untuk satu orang.
- **Dana tidak bisa ditahan sebelum perekrutan selesai.** Tagihan sudah ada sejak pelamar
  pertama diterima, jadi tanpa penjaga itu pekerja yang direkrut belakangan tidak akan
  pernah punya activity.
- **`reviews` unique-nya `(task_id, reviewer_id, reviewee_id)`.** Dengan kunci lama,
  pemberi kerja yang merekrut 30 orang hanya bisa menilai satu dari mereka.
- `POST /tasks/{task}/start` menurunkan target ke jumlah yang sudah diterima lalu menutup
  lelang — untuk pekerjaan bertanggal yang tidak mendapat pelamar sebanyak targetnya.

## Auth & security

Three layers, all declared in `routes/api.php` so the whole rule set reads in one file:

1. `auth:sanctum` — the token is valid
2. `abilities:token:access` — it is an **access** token, not a long-lived one.
   Without this layer a long-lived token could call the entire API, and it lives
   for 30 days. The Sanctum ability aliases are registered in `bootstrap/app.php`;
   they are **not** auto-registered in Laravel 11+, and a missing alias fails as an
   unknown middleware rather than loudly.
3. `throttle:<limiter>` — every route is rate limited. Limiters live in
   `RateLimitServiceProvider`, numbers in `config/sekarya.php`.

**Registration never activates an account.** `POST /auth/register` returns 202 with no
tokens; the account sits at `pending_verification` until a 6-digit code from email is
accepted by `POST /auth/verify-email`, which is the only path to `active` + a token pair.

**Token pair.** `access` lives 8 hours and is the only token that can call app
endpoints. `long_lived` lives 30 days and can *only* call `POST /auth/refresh`.
Refreshing revokes the previous access token, so the old one dies the moment a new one
is issued. Logout revokes everything, including the long-lived token — revoking only
the access token would leave a credential that can mint new access.

Things that must not be "tidied up":

- **`users.status` default is `pending_verification`, and `status` is deliberately NOT
  mass-assignable.** A privilege-bearing column must never be settable from an attribute
  array, and its default must be the least-privileged state. This combination already
  caught a real bug: `User::create(['status' => …])` silently dropped the value and the
  old `active` default let registration bypass verification entirely.
- **Failed verification attempts are recorded OUTSIDE the transaction.** Incrementing
  inside a transaction that then throws is rolled back, which silently disables the
  per-code attempt limit and leaves a 6-digit code brute-forceable.
- **`DB_TIMEZONE=+00:00`.** MySQL's session timezone otherwise follows the host
  (Asia/Jakarta here, +7), so SQL `now()` and PHP `now()` disagree by seven hours and
  every server-evaluated timestamp (`CURRENT_TIMESTAMP` defaults, `NOW()` in WHERE)
  uses a different clock from the code that wrote the data.
- **CORS allowed origins come from `CORS_ALLOWED_ORIGINS`**, never `*`, and
  `supports_credentials` stays false — this API uses Bearer tokens, not cookies.
- Login, resend-code and verify-email return **identical responses whether or not the
  email exists**. Otherwise they become account-enumeration tools.

## Observability (Axiom)

Full guide: `docs/OBSERVABILITY.md`. Reusable rules and the leak table live in the
skill: `references/observability.md`. Config SSOT: `config/axiom.php`. Off by
default (`AXIOM_ENABLED=false`), and hard-off in `phpunit.xml`.

`app/Logging/Axiom/Redactor.php` is the **only** way data leaves for Axiom, and it
is a security control, not a utility. Three layers, all three required: key
denylist, **value-pattern scrubbing** (tokens, JWT, PEM, e-mail, phone, 16-digit
IDs), and size caps. Layer 2 is what covers the gap layer 1 leaves — every time
a payload gains a field, the denylist is behind and the patterns are not.
Headers use an **allowlist**, because `Authorization` and `Cookie` are always there.

Things that must not be "tidied up":

- **The e-mail subject is never logged.** In this app it reads
  `Kode verifikasi Sekarya: 623862` — it *contains* the credential.
- **Query bindings are never logged**, only the SQL with `?`. The bindings are
  where the e-mail, the NIK and the password hash actually are.
- **Stack traces are rebuilt from `getTrace()`, never `getTraceAsString()`**,
  which includes scalar call arguments — `Hash::check('rahasia', …)` verbatim.
- **Uploaded filenames are dropped**, only size + MIME are kept.
  `KTP_Budi_Prasetyo.jpg` names the person in metadata.
- **`AxiomLogger` must stay a singleton** — one buffer per process, or the
  `request_id` that ties a request's events together differs per source.
- **Request start time lives on `AxiomLogger`, not the middleware.** Laravel
  builds a *new* middleware instance for `terminate()`, so instance state does
  not survive. When it did, every request measured as billions of ms, so every
  request counted as "slow", and since slow requests are never sampled, sampling
  silently stopped working.
- **`config('axiom.auth_routes')` is how auth outcomes get logged.** This app
  never calls `Auth::attempt()`, so Laravel's `Login`/`Failed` events never fire.
- **One `AXIOM_*` block in `.env`.** Duplicate keys make the *last* one win, so a
  leftover empty placeholder silently blanks a working token — the symptom is
  "kredensial belum diisi" while the token is plainly in the file.
  `php artisan sekarya:axiom` now reports duplicated keys by name.

Prove it rather than trusting it — run this after touching `config/axiom.php` or
after any endpoint gains a field:

```bash
php artisan sekarya:axiom --audit   # prints the filtered result, sends nothing
php artisan sekarya:axiom --ping    # one probe event to Axiom
```

## Layer rules (short form)

- Actions take a DTO and an explicit actor. No `request()`, `auth()`, `abort()`, `response()`.
- Actions throw `App\Exceptions\Domain\DomainException` subclasses. `bootstrap/app.php` is the
  single place that turns them into status codes.
- `UserRole::extrasKeys()` is the SSOT for which `extras` keys are valid per role.
  Validation, DTO sanitisation and the Transformer all read from it.
- Resources are the disclosure boundary: `npwp` and `gateway_payload` never leave the API.
  Tests assert this.
- Every `cursorPaginate()` query orders by `created_at` **and** `id` (use `scopeLatestFirst`).

## Commands

```bash
# Sekali saat setup: buat database-nya lebih dulu
#   CREATE DATABASE sekarya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php artisan migrate:fresh --seed  # 13 tabel + kategori & skills
php artisan serve                 # http://localhost:8000
php artisan test                  # 716 tests
composer test-report              # coverage/html + junit + testdox (lihat tests/README.md)
php artisan sekarya:axiom --audit # buktikan penyaringan PII sebelum kirim apa pun
php artisan test tests/Unit       # fast tier
./vendor/bin/pint                 # format (run before committing)
php artisan route:list --path=api
php artisan sekarya:demo --fresh     # seed fixtures + print dev tokens
bash docs/smoke.sh                # 95 live HTTP assertions, self-hosting server
```

## API contract

`docs/openapi.yaml` is the contract — OpenAPI **3.1**, hand-maintained. No swagger-php /
l5-swagger annotation package is installed, and none should be: the spec is the artefact,
not a by-product of docblocks.

```bash
npx --yes -p @redocly/cli redocly lint docs/openapi.yaml            # must stay clean
npx --yes -p @redocly/cli redocly build-docs docs/openapi.yaml \
  -o docs/api.html                                                  # regenerate the HTML
```

Served at `/docs` and `/docs/openapi.yaml` by `routes/web.php`, registered **only outside
production** — `docs/` lives outside `public/` so it is unreachable otherwise. A test boots
a production env in a subprocess and asserts the routes are gone. `docs/api.html` is
generated; gitignore it once the repo is initialised.

**Documentation drift is a test failure, not a habit.**
`tests/Feature/Docs/ApiDocumentationTest.php` asserts, in both directions, that every
`/api/v1` route has an operation in `docs/openapi.yaml`, that the spec promises no
endpoint that does not exist, that every domain error code is in the `DomainError` enum,
and that every endpoint appears in the `docs/API.md` summary table with the right count.
Add a route without documenting it and the suite names it.

`docs/API.md` is the human walkthrough. **When you change an API Resource, update
`docs/openapi.yaml` in the same commit.** Two rules the spec encodes and tests enforce:

- Validation failures return `{message, errors}`; business failures return
  `{message, code, context?}`. The two shapes stay disjoint so clients branch on `code`,
  never on `message`.
- `extras` always serialises as an object (`{}` when empty, never `[]`). A key that flips
  between array and object breaks generated clients.

## Scaffold a new feature slice

```bash
bash ~/.claude/skills/laravel-action-api/scripts/scaffold_feature.sh <Domain> <Verb>
```

Generates DTO + Request + Action + Controller + Resource + test stub, then finish the TODOs
and register the route.

## Reference implementation

`Activity` create/list is the worked example of every rule above — read it before adding a
new slice:

- `app/Actions/Activity/CreateActivityAction.php` — the Transaction activation gate
- `app/Actions/Activity/ListActivitiesAction.php` — cursor pagination
- `app/Http/Resources/Api/V1/UserProfileResource.php` — role-conditional `extras` mapping
- `tests/Unit/Actions/Activity/` — how Action tests are written (DTO built directly)
