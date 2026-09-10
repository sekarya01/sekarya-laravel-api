#!/usr/bin/env bash
#
# Smoke test end-to-end untuk Sekarya API.
#
#   bash docs/smoke.sh              # jalankan server sendiri di port 8123
#   bash docs/smoke.sh 8000         # pakai server yang sudah jalan di port itu
#
# Keluar 0 kalau semua lolos, 1 kalau ada yang gagal.
#
# PERINGATAN: menjalankan `migrate:fresh --seed` — seluruh data dev hilang.
#
# Akun dibuat lewat alur auth yang sebenarnya (register -> kode dari log email
# -> verify), bukan token yang di-seed. Jadi kalau auth rusak, smoke test ini
# gagal di langkah awal alih-alih diam-diam melewatinya.

set -uo pipefail

PORT="${1:-8123}"
OWN_SERVER=0
SERVER_PID=""
BASE="http://127.0.0.1:${PORT}/api/v1"
LOG=storage/logs/laravel.log
PASS=0
FAIL=0

cd "$(dirname "$0")/.." || exit 1

if [[ -t 1 ]]; then
    G=$'\033[32m'; R=$'\033[31m'; Y=$'\033[33m'; DIM=$'\033[2m'; N=$'\033[0m'
else
    G=""; R=""; Y=""; DIM=""; N=""
fi

cleanup() {
    if [[ "$OWN_SERVER" == "1" && -n "$SERVER_PID" ]]; then
        kill "$SERVER_PID" 2>/dev/null
        echo "${DIM}server dihentikan (pid ${SERVER_PID})${N}"
    fi
}
trap cleanup EXIT

ACC='Accept: application/json'
CT='Content-Type: application/json'

# ── helper ───────────────────────────────────────────────────────────────────

# check <label> <status> <ekspresi|-> <nilai|-> <argumen curl...>
#
# Ekspresi adalah Python, dievaluasi dengan respons ter-parse sebagai `d`.
# Bisa lookup (d['code']), hitungan (len(d['data'])), atau predikat
# ('total' in d['meta']).
check() {
    local label="$1" want_status="$2" expr="$3" want_value="$4"
    shift 4

    local raw body status
    raw="$(curl -s -w $'\n%{http_code}' "$@")"
    status="$(printf '%s' "$raw" | tail -1)"
    body="$(printf '%s' "$raw" | sed '$d')"

    local problems=""
    [[ "$status" != "$want_status" ]] && problems="status ${status}, harus ${want_status}"

    if [[ "$expr" != "-" ]]; then
        local got
        got="$(printf '%s' "$body" | python3 -c "
import json, sys
try:
    d = json.load(sys.stdin)
except Exception:
    print('<bukan json>'); raise SystemExit
try:
    print(json.dumps(eval('''$expr''')))
except Exception as e:
    print('<gagal eval: %s>' % type(e).__name__)
" 2>/dev/null)"
        if [[ "$got" != "$want_value" ]]; then
            [[ -n "$problems" ]] && problems="${problems}; "
            problems="${problems}${expr} => ${got}, harus ${want_value}"
        fi
    fi

    if [[ -z "$problems" ]]; then
        printf '  %sLULUS%s %s %s(%s)%s\n' "$G" "$N" "$label" "$DIM" "$status" "$N"
        PASS=$((PASS + 1))
    else
        printf '  %sGAGAL%s %s\n        %s%s%s\n' "$R" "$N" "$label" "$DIM" "$problems" "$N"
        printf '        %sbody: %s%s\n' "$DIM" "${body:0:200}" "$N"
        FAIL=$((FAIL + 1))
    fi
}

ok()   { printf '  %sLULUS%s %s\n' "$G" "$N" "$1"; PASS=$((PASS + 1)); }
bad()  { printf '  %sGAGAL%s %s\n' "$R" "$N" "$1"; FAIL=$((FAIL + 1)); }
json() { printf '%s' "$1" | python3 -c "import json,sys;d=json.load(sys.stdin);print(eval('''$2'''))" 2>/dev/null; }

# Kosongkan ember rate limit.
#
# Skrip ini butuh TUJUH pendaftaran, sementara limiter `register` hanya
# mengizinkan lima per menit per IP — dan seluruh permintaan di sini datang
# dari 127.0.0.1. Selama ini ia lolos karena untung-untungan: kalau
# pendaftaran keenam kebetulan jatuh sesudah jendela 60 detik bergulir, semua
# hijau; kalau tidak, ia dijawab 429, tokennya kosong, dan yang TERLIHAT
# adalah empat pemeriksaan lelang yang gagal empat langkah kemudian tanpa
# menyebut pendaftaran sama sekali.
#
# Yang direset embernya, BUKAN batasnya: angka limiter tetap sama persis
# seperti produksi, dan pemeriksaan rate limit di bagian 5 tetap membuktikan
# limiter itu benar-benar hidup. Yang dihapus hanya jejak dari fase sebelumnya
# — kebutuhan tujuh akun dalam satu menit adalah sifat skrip ini, bukan sifat
# kliennya.
reset_rate_limits() { php artisan cache:clear >/dev/null 2>&1; }

# Daftar + verifikasi lewat alur nyata; kode dibaca dari log email.
# Menulis respons pasangan token ke stdout.
#
# GAGALNYA HARUS BERISIK. Dulu fungsi ini menelan status pendaftaran ke
# /dev/null, jadi satu pendaftaran yang ditolak (paling sering `429` — batas
# laju register hanya 5 per menit per IP) menghasilkan token kosong, dan yang
# terlihat adalah EMPAT pemeriksaan lelang yang gagal empat langkah kemudian
# dengan pesan yang tidak menyebut pendaftaran sama sekali.
signup() {
    : > "$LOG"
    local reg_status
    reg_status="$(curl -s -X POST "$BASE/auth/register" -H "$ACC" -H "$CT" \
        -d "{\"name\":\"$1\",\"email\":\"$2\",\"phone\":\"$3\",\"password\":\"RahasiaKuat2026\",\"password_confirmation\":\"RahasiaKuat2026\",\"city\":\"Jakarta\"}" \
        -o /dev/null -w '%{http_code}')"

    if [[ "$reg_status" != "202" ]]; then
        bad "signup <$2>: register menjawab ${reg_status}, harus 202$(
            [[ "$reg_status" == "429" ]] && printf ' — batas laju register (%s/menit per IP) tercapai' "${SEKARYA_RL_REGISTER:-5}"
        )" >&2
        return 1
    fi

    local code
    code="$(grep -oE '\*\*[0-9]{6}\*\*' "$LOG" | head -1 | tr -d '*')"

    if [[ -z "$code" ]]; then
        bad "signup <$2>: kode verifikasi tidak terbaca dari ${LOG} (MAIL_MAILER harus 'log')" >&2
        return 1
    fi

    local verified
    verified="$(curl -s -X POST "$BASE/auth/verify-email" -H "$ACC" -H "$CT" \
        -d "{\"email\":\"$2\",\"code\":\"$code\"}")"

    if [[ -z "$(json "$verified" "d['data']['access_token']")" ]]; then
        bad "signup <$2>: verify-email tidak mengembalikan token: ${verified:0:200}" >&2
        return 1
    fi

    printf '%s' "$verified"
}

# ── 1. reset ─────────────────────────────────────────────────────────────────
echo "${Y}==>${N} Reset database (data dev hilang)"
if ! php artisan migrate:fresh --seed --no-interaction >/dev/null 2>&1; then
    echo "${R}FATAL${N} migrate:fresh gagal. Cek koneksi MySQL dan DB_DATABASE di .env."
    exit 1
fi
grep -q '^MAIL_MAILER=log' .env || \
    echo "${Y}CATATAN${N} MAIL_MAILER bukan 'log' — kode verifikasi tidak terbaca dari log."

# Akun pengelola TIDAK bisa dibuat lewat API — tidak ada endpoint pendaftaran
# pengelola, dan seeder-nya sengaja tidak ada (berkas seeder dan berkas
# pemasangan SQL sama-sama dilacak git). Satu-satunya jalannya perintah ini.
ADMIN_PASS='RahasiaKuatSekali99!'
php artisan sekarya:admin create --name="Super Admin" --email=super@sekarya.test \
    --password="$ADMIN_PASS" --no-interaction >/dev/null 2>&1 \
    || { echo "${R}FATAL${N} tidak bisa membuat super_admin"; exit 1; }
php artisan sekarya:admin create --role=admin --name="Verifikator" --email=verif@sekarya.test \
    --password="$ADMIN_PASS" --no-interaction >/dev/null 2>&1 \
    || { echo "${R}FATAL${N} tidak bisa membuat admin"; exit 1; }

# ── 2. server ────────────────────────────────────────────────────────────────
if curl -sf -o /dev/null "http://127.0.0.1:${PORT}/up" 2>/dev/null; then
    echo "${Y}==>${N} Memakai server yang sudah jalan di port ${PORT}"
else
    echo "${Y}==>${N} Menjalankan php artisan serve di port ${PORT}"
    php artisan serve --port="$PORT" >/dev/null 2>&1 &
    SERVER_PID=$!
    OWN_SERVER=1
    for _ in $(seq 1 40); do
        curl -sf -o /dev/null "http://127.0.0.1:${PORT}/up" 2>/dev/null && break
        perl -e 'select(undef,undef,undef,0.25)' 2>/dev/null || true
    done
    if ! curl -sf -o /dev/null "http://127.0.0.1:${PORT}/up" 2>/dev/null; then
        echo "${R}FATAL${N} server tidak naik di port ${PORT}"
        exit 1
    fi
fi

# ── 3. autentikasi ───────────────────────────────────────────────────────────
echo
echo "${Y}==>${N} Autentikasi"

check "API tanpa token ditolak" 401 "d['code']" '"unauthenticated"' \
    -H "$ACC" "$BASE/categories"

: > "$LOG"
REG="$(curl -s -w $'\n%{http_code}' -X POST "$BASE/auth/register" -H "$ACC" -H "$CT" \
    -d '{"name":"Budi Prasetyo","email":"budi@sekarya.test","phone":"+628111222333","password":"RahasiaKuat2026","password_confirmation":"RahasiaKuat2026","city":"Jakarta"}')"
REG_CODE="$(printf '%s' "$REG" | tail -1)"
REG_BODY="$(printf '%s' "$REG" | sed '$d')"
REG_STATUS="$(json "$REG_BODY" "d['data']['status']")"
if [[ "$REG_CODE" == "202" && "$REG_STATUS" == "pending_verification" ]]; then
    ok "register tidak mengaktifkan akun ${DIM}(202, ${REG_STATUS})${N}"
else
    bad "register: status http ${REG_CODE}, status akun ${REG_STATUS}"
fi

check "register tidak mengembalikan token" 202 "'access_token' in json.dumps(d)" 'false' \
    -X POST "$BASE/auth/register" -H "$ACC" -H "$CT" \
    -d '{"name":"Cek Token","email":"cektoken@sekarya.test","phone":"+628999888777","password":"RahasiaKuat2026","password_confirmation":"RahasiaKuat2026"}'

check "login sebelum verifikasi ditolak" 403 "d['code']" '"email_not_verified"' \
    -X POST "$BASE/auth/login" -H "$ACC" -H "$CT" \
    -d '{"email":"budi@sekarya.test","password":"RahasiaKuat2026"}'

VERIF_CODE="$(grep -oE '\*\*[0-9]{6}\*\*' "$LOG" | head -1 | tr -d '*')"
[[ -z "$VERIF_CODE" ]] && { echo "${R}FATAL${N} kode verifikasi tidak ditemukan di ${LOG}"; exit 1; }

check "kode salah menurunkan sisa percobaan" 422 "d['context']['attempts_left']" '4' \
    -X POST "$BASE/auth/verify-email" -H "$ACC" -H "$CT" \
    -d '{"email":"budi@sekarya.test","code":"000000"}'
check "kode salah kedua menurunkan lagi" 422 "d['context']['attempts_left']" '3' \
    -X POST "$BASE/auth/verify-email" -H "$ACC" -H "$CT" \
    -d '{"email":"budi@sekarya.test","code":"000000"}'

PAIR="$(curl -s -X POST "$BASE/auth/verify-email" -H "$ACC" -H "$CT" \
    -d "{\"email\":\"budi@sekarya.test\",\"code\":\"$VERIF_CODE\"}")"
ACCESS="$(json "$PAIR" "d['data']['access_token']")"
LONG="$(json "$PAIR" "d['data']['long_lived_token']")"
TTL="$(json "$PAIR" "d['data']['access_expires_in_seconds']")"
ACTIVE="$(json "$PAIR" "d['data']['user']['status']")"

if [[ "$ACTIVE" == "active" && -n "$ACCESS" && -n "$LONG" ]]; then
    ok "verifikasi mengaktifkan akun + menerbitkan 2 token"
else
    bad "verifikasi: status akun=${ACTIVE}"
    echo "${R}Tidak bisa lanjut tanpa token.${N}"
    exit 1
fi

if [[ "$TTL" -gt 28700 && "$TTL" -le 28800 ]]; then
    ok "access token berumur 8 jam ${DIM}(${TTL}s)${N}"
else
    bad "access TTL ${TTL}s, harus ~28800s"
fi

AUTH=(-H "Authorization: Bearer ${ACCESS}" -H "$ACC")
AUTH_LONG=(-H "Authorization: Bearer ${LONG}" -H "$ACC")

# ── 4. pemisahan jenis token ─────────────────────────────────────────────────
echo
echo "${Y}==>${N} Pemisahan jenis token"

check "access token boleh panggil API" 200 "d['data']['status']" '"active"' \
    "${AUTH[@]}" "$BASE/me"
check "long_lived DITOLAK di endpoint aplikasi" 403 - - \
    "${AUTH_LONG[@]}" "$BASE/me"
check "access token DITOLAK untuk refresh" 403 - - \
    -X POST "${AUTH[@]}" "$BASE/auth/refresh"

NEW_ACCESS="$(curl -s -X POST "${AUTH_LONG[@]}" "$BASE/auth/refresh" \
    | python3 -c "import json,sys;print(json.load(sys.stdin)['data']['access_token'])" 2>/dev/null)"

check "access token LAMA mati setelah refresh" 401 "d['code']" '"unauthenticated"' \
    "${AUTH[@]}" "$BASE/me"
check "access token BARU berlaku" 200 - - \
    -H "Authorization: Bearer ${NEW_ACCESS}" -H "$ACC" "$BASE/me"

ACCESS="$NEW_ACCESS"
AUTH=(-H "Authorization: Bearer ${ACCESS}" -H "$ACC")

# ── 4b. sesi pengelola ───────────────────────────────────────────────────────
#
# Populasi token yang BERBEDA. Akun pengelola ada di tabelnya sendiri dengan
# guard-nya sendiri, jadi kedua arah harus ditolak — dan itu bergantung pada
# satu baris config (`provider` pada guard) yang kalau hilang membuat Sanctum
# meloloskan pemilik token jenis apa pun TANPA galat apa pun.
echo
echo "${Y}==>${N} Sesi pengelola"

ADMIN_PAIR="$(curl -s -X POST "$BASE/admin/auth/login" -H "$ACC" -H "$CT" \
    -d "{\"email\":\"super@sekarya.test\",\"password\":\"${ADMIN_PASS}\"}")"
ADMIN_TOKEN="$(json "$ADMIN_PAIR" "d['data']['access_token']")"
ADMIN_LONG_TOKEN="$(json "$ADMIN_PAIR" "d['data']['long_lived_token']")"
ADMIN_ROLE="$(json "$ADMIN_PAIR" "d['data']['admin']['role']")"

if [[ "$ADMIN_ROLE" == "super_admin" && -n "$ADMIN_TOKEN" ]]; then
    ok "super_admin masuk + menerima 2 token"
else
    bad "login pengelola gagal (role=${ADMIN_ROLE})"
    echo "${R}Tidak bisa lanjut tanpa token pengelola.${N}"
    exit 1
fi

ADMIN=(-H "Authorization: Bearer ${ADMIN_TOKEN}" -H "$ACC")
ADMIN_LONG=(-H "Authorization: Bearer ${ADMIN_LONG_TOKEN}" -H "$ACC")

VERIF_PAIR="$(curl -s -X POST "$BASE/admin/auth/login" -H "$ACC" -H "$CT" \
    -d "{\"email\":\"verif@sekarya.test\",\"password\":\"${ADMIN_PASS}\"}")"
VERIF_TOKEN="$(json "$VERIF_PAIR" "d['data']['access_token']")"
VERIF_ADMIN=(-H "Authorization: Bearer ${VERIF_TOKEN}" -H "$ACC")

check "sandi pengelola salah = alamat tak dikenal" 401 "d['code']" '"invalid_credentials"' \
    -X POST "$BASE/admin/auth/login" -H "$ACC" -H "$CT" \
    -d '{"email":"super@sekarya.test","password":"salah"}'
check "alamat pengelola tak dikenal, jawaban sama" 401 "d['code']" '"invalid_credentials"' \
    -X POST "$BASE/admin/auth/login" -H "$ACC" -H "$CT" \
    -d '{"email":"tidakada@sekarya.test","password":"RahasiaKuatSekali99!"}'

check "token PENGGUNA ditolak di /admin" 401 "d['code']" '"unauthenticated"' \
    "${AUTH[@]}" "$BASE/admin/me"
check "token PENGELOLA ditolak di endpoint pengguna" 401 "d['code']" '"unauthenticated"' \
    "${ADMIN[@]}" "$BASE/me"
check "long_lived pengelola ditolak di /admin" 403 - - \
    "${ADMIN_LONG[@]}" "$BASE/admin/me"
check "pengelola melihat dirinya sendiri" 200 "d['data']['is_super_admin']" 'true' \
    "${ADMIN[@]}" "$BASE/admin/me"

# ── 5. rate limit & CORS ─────────────────────────────────────────────────────
echo
echo "${Y}==>${N} Rate limit & CORS"

RL_HIT=0
for i in $(seq 1 8); do
    code="$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/auth/login" \
        -H "$ACC" -H "$CT" -d '{"email":"tidakada@sekarya.test","password":"salah"}')"
    if [[ "$code" == "429" ]]; then RL_HIT=$i; break; fi
done
if [[ "$RL_HIT" -ge 2 && "$RL_HIT" -le 8 ]]; then
    ok "login dibatasi, 429 pada percobaan ke-${RL_HIT}"
else
    bad "rate limit login tidak aktif (tidak ada 429 dalam 8 percobaan)"
fi

CORS_OK="$(curl -s -i -X OPTIONS "$BASE/me" \
    -H 'Origin: http://localhost:3000' -H 'Access-Control-Request-Method: GET' \
    2>/dev/null | grep -ci 'access-control-allow-origin' || true)"
CORS_BAD="$(curl -s -i "$BASE/categories" \
    -H 'Origin: https://evil.example' -H "$ACC" \
    2>/dev/null | grep -ci 'access-control-allow-origin' || true)"
if [[ "$CORS_OK" -ge 1 && "$CORS_BAD" -eq 0 ]]; then
    ok "CORS: origin terdaftar diizinkan, origin asing tidak"
else
    bad "CORS: terdaftar=${CORS_OK} asing=${CORS_BAD} (harus >=1 dan 0)"
fi

# ── 5b. identitas & profil pekerja ───────────────────────────────────────────
#
# Dua tabel, satu orang. `gender`/`birth_date` di `users`; nama tampilan,
# kontak, alamat kerja, lokasi dan reputasi di `user_workers`. Yang diuji di
# sini justru sambungannya: warisan dari akun, `null` yang mengembalikan
# warisan, dan umur yang dihitung — bukan disimpan.
echo
echo "${Y}==>${N} Identitas & profil pekerja"

check "jenis kelamin & tanggal lahir tersimpan, umur ikut" 200 \
    "[d['data']['gender'], d['data']['birth_date'], d['data']['age'] is not None]" \
    '["male", "1995-03-02", true]' \
    -X PATCH "${AUTH[@]}" -H "$CT" \
    -d '{"gender":"male","birth_date":"1995-03-02"}' "$BASE/me"

check "umur TIDAK diterima dari klien" 200 "d['data']['age']" \
    "$(python3 -c "
from datetime import date
b = date(1995, 3, 2); t = date.today()
print(t.year - b.year - ((t.month, t.day) < (b.month, b.day)))")" \
    -X PATCH "${AUTH[@]}" -H "$CT" -d '{"age":99}' "$BASE/me"

check "gender di luar dua nilai ditolak" 422 "'gender' in d['errors']" 'true' \
    -X PATCH "${AUTH[@]}" -H "$CT" -d '{"gender":"laki-laki"}' "$BASE/me"

check "umur di bawah batas minimum ditolak" 422 "'birth_date' in d['errors']" 'true' \
    -X PATCH "${AUTH[@]}" -H "$CT" \
    -d "{\"birth_date\":\"$(date -v-10y +%Y-%m-%d 2>/dev/null || date -d '10 years ago' +%Y-%m-%d)\"}" \
    "$BASE/me"

check "format tanggal lahir hanya YYYY-MM-DD" 422 "'birth_date' in d['errors']" 'true' \
    -X PATCH "${AUTH[@]}" -H "$CT" -d '{"birth_date":"02-03-1995"}' "$BASE/me"

check "profil pekerja kosong mewarisi dari akun, tanpa membuat baris" 200 \
    "[d['data']['configured'], d['data']['own']['display_name'], d['data']['gender']]" \
    '[false, null, "male"]' \
    "${AUTH[@]}" "$BASE/me/worker"

check "PUT pertama MEMBUAT profilnya" 201 \
    "[d['data']['configured'], d['data']['name'], d['data']['own']['display_name']]" \
    '[true, "Budi Tukang AC", "Budi Tukang AC"]' \
    -X PUT "${AUTH[@]}" -H "$CT" \
    -d '{"display_name":"Budi Tukang AC","latitude":-6.2088,"longitude":106.8456,"radius_km":15}' \
    "$BASE/me/worker"

check "PUT kedua hanya mengubah" 200 "d['data']['work_location']['radius_km']" '20' \
    -X PUT "${AUTH[@]}" -H "$CT" -d '{"radius_km":20}' "$BASE/me/worker"

check "field yang tidak disebut tidak tersentuh" 200 \
    "d['data']['own']['display_name']" '"Budi Tukang AC"' \
    "${AUTH[@]}" "$BASE/me/worker"

check "null mengembalikan field ke warisan akun" 200 \
    "[d['data']['own']['display_name'], d['data']['name'] != 'Budi Tukang AC']" \
    '[null, true]' \
    -X PUT "${AUTH[@]}" -H "$CT" -d '{"display_name":null}' "$BASE/me/worker"

check "alamat kerja menggantikan alamat akun SELURUHNYA" 200 \
    "[d['data']['address']['city'], d['data']['address']['address_line']]" \
    '["Surabaya", null]' \
    -X PUT "${AUTH[@]}" -H "$CT" -d '{"city":"Surabaya"}' "$BASE/me/worker"

check "lintang tanpa bujur ditolak" 422 "'longitude' in d['errors']" 'true' \
    -X PUT "${AUTH[@]}" -H "$CT" -d '{"latitude":-6.2088,"longitude":null}' "$BASE/me/worker"

check "reputasi TIDAK bisa dikirim klien" 200 \
    "[d['data']['as_worker']['tasks_completed'], d['data']['as_worker']['rating_avg']]" \
    '[0, 0]' \
    -X PUT "${AUTH[@]}" -H "$CT" \
    -d '{"tasks_completed":999,"worker_rating_avg":5}' "$BASE/me/worker"

check "identitas TIDAK bisa diubah lewat profil pekerja" 200 "d['data']['gender']" '"male"' \
    -X PUT "${AUTH[@]}" -H "$CT" \
    -d '{"gender":"female","birth_date":"1970-01-01"}' "$BASE/me/worker"

check "profil pekerja butuh token" 401 "d['code']" '"unauthenticated"' \
    -H "$ACC" "$BASE/me/worker"

ME_ID="$(json "$(curl -s "${AUTH[@]}" "$BASE/me")" "d['data']['id']")"

# Akun yang sengaja TIDAK dilengkapi identitasnya — dipakai membuktikan pintu
# profil pekerja benar-benar tertutup.
reset_rate_limits
INCOMPLETE_TOKEN="$(json "$(signup 'Tanpa Identitas' 'tanpa.identitas@sekarya.test' '+628999000111')" "d['data']['access_token']")"
W_INCOMPLETE=(-H "Authorization: Bearer ${INCOMPLETE_TOKEN}" -H "$ACC")

# Profil pekerja saja BELUM siap kerja: gerbangnya persetujuan pengelola atas
# identitas, dan itu tidak bisa diberikan sendiri.
check "profil pekerja ada tapi belum siap kerja" 200 "d['data']['ready_to_work']" 'false' \
    "${AUTH[@]}" "$BASE/me"

check "belum terverifikasi berarti belum masuk daftar pekerja" 200 \
    "[w['id'] for w in d['data'] if w['id'] == '${ME_ID}']" '[]' \
    "${AUTH[@]}" "$BASE/workers"

# Gerbangnya dibuka lewat ALUR SUNGGUHAN: pengguna mengajukan, pengelola
# menyetujui. Bukan baris yang disuntikkan ke basis data — yang diuji justru
# bahwa persetujuan itu tidak bisa diberikan sendiri.
curl -s -X POST "$BASE/me/verifications" "${AUTH[@]}" -H "$CT" -d '{
  "type":"identity",
  "id_card_photo_path":"verifications/ktp-pemilik.jpg",
  "selfie_photo_path":"verifications/selfie-pemilik.jpg",
  "document_number":"3174099988877766",
  "name_on_document":"Pemilik Akun"
}' -o /dev/null

check "mengajukan saja belum membuka siap kerja" 200 "d['data']['ready_to_work']" 'false' \
    "${AUTH[@]}" "$BASE/me"

MY_VER="$(json "$(curl -s "$BASE/admin/verifications" "${ADMIN[@]}")" "d['data'][0]['id']")"
check "pengelola menyetujui identitas pemilik akun" 200 "d['data']['status']" '"verified"' \
    -X POST "$BASE/admin/verifications/$MY_VER/approve" "${ADMIN[@]}"

check "siap kerja menyala SESUDAH pengelola menyetujui" 200 "d['data']['ready_to_work']" 'true' \
    "${AUTH[@]}" "$BASE/me"

check "dan barulah ia muncul di daftar pekerja" 200 \
    "[w['ready_to_work'] for w in d['data'] if w['id'] == '${ME_ID}']" '[true]' \
    "${AUTH[@]}" "$BASE/workers"

check "identitas belum lengkap = profil pekerja ditolak" 422 \
    "[d['code'], d['context']['missing']]" '["profile_incomplete", ["gender", "birth_date"]]' \
    -X PUT "${W_INCOMPLETE[@]}" -H "$CT" -d '{"radius_km":10}' "$BASE/me/worker"

check "daftar pekerja memakai cursor, bukan offset" 200 \
    "['next_cursor' in d['meta'], 'total' in d['meta'], 'current_page' in d['meta']]" \
    '[true, false, false]' \
    "${AUTH[@]}" "$BASE/workers?per_page=1"

check "penyaring kota memakai alamat TERPAKAI" 200 "len(d['data']) >= 1" 'true' \
    "${AUTH[@]}" "$BASE/workers?city=Surabaya"

check "daftar pekerja tidak membocorkan tanggal lahir" 200 \
    "[k for k in d['data'][0] if k in ('birth_date','email','phone')]" '[]' \
    "${AUTH[@]}" "$BASE/workers"

check "gender di luar dua nilai ditolak di daftar" 422 "'gender' in d['errors']" 'true' \
    "${AUTH[@]}" "$BASE/workers?gender=perempuan"

check "per_page di atas maksimum ditolak di daftar pekerja" 422 "'per_page' in d['errors']" 'true' \
    "${AUTH[@]}" "$BASE/workers?per_page=500"

check "daftar pekerja butuh token" 401 "d['code']" '"unauthenticated"' \
    -H "$ACC" "$BASE/workers"

# ── 6. katalog ───────────────────────────────────────────────────────────────
echo
echo "${Y}==>${N} Katalog"
check "kategori tersedia" 200 "len(d['data']) >= 9" 'true' "${AUTH[@]}" "$BASE/categories"
check "harga referensi ditandai belum dari data nyata" 200 \
    "d['data'][0]['reference_price']['from_real_data']" 'false' "${AUTH[@]}" "$BASE/categories"
check "keahlian tersedia" 200 "len(d['data']) >= 40" 'true' "${AUTH[@]}" "$BASE/skills"

# ── 7. alur inti ─────────────────────────────────────────────────────────────
echo
echo "${Y}==>${N} Alur inti: task -> lelang -> deal -> transfer -> selesai"

reset_rate_limits
WORKER_TOKEN="$(json "$(signup 'Siti Penerima' 'siti@sekarya.test' '+628222333444')" "d['data']['access_token']")"
OTHER_TOKEN="$(json "$(signup 'Agus Penawar' 'agus@sekarya.test' '+628333444555')" "d['data']['access_token']")"
# Pelamar ketiga: dipakai untuk membuktikan kuota lamaran benar-benar menutup.
EXTRA_TOKEN="$(json "$(signup 'Rina Kelebihan' 'rina@sekarya.test' '+628444555666')" "d['data']['access_token']")"
W=(-H "Authorization: Bearer ${WORKER_TOKEN}" -H "$ACC")
O=(-H "Authorization: Bearer ${OTHER_TOKEN}" -H "$ACC")
X=(-H "Authorization: Bearer ${EXTRA_TOKEN}" -H "$ACC")

TASK_JSON="$(curl -s -X POST "$BASE/tasks" "${AUTH[@]}" -H "$CT" -d '{
  "category_id":2,
  "title":"Cuci AC 2 unit di rumah",
  "description":"Servis AC split, freon dan cuci evaporator.",
  "budget_min":150000,
  "city":"Jakarta","latitude":-6.1754,"longitude":106.8272,
  "skills":["cuci-ac"],"publish_now":true,
  "options":[{"label":"Bawa alat sendiri","value":true}]
}')"
TASK="$(json "$TASK_JSON" "d['data']['id']")"
if [[ -z "$TASK" ]]; then
    bad "buat task: ${TASK_JSON:0:200}"
    echo "${R}Tidak bisa lanjut tanpa task.${N}"
    exit 1
fi
ok "buat task ${DIM}${TASK}${N}"

check "budget_max null saat tidak diisi" 200 "d['data']['budget']['max']" 'null' \
    "${AUTH[@]}" "$BASE/tasks/$TASK"
check "harga referensi tersalin ke task" 200 \
    "d['data']['budget']['reference_median'] is not None" 'true' \
    "${AUTH[@]}" "$BASE/tasks/$TASK"

BID="$(json "$(curl -s -X POST "$BASE/tasks/$TASK/bids" "${W[@]}" -H "$CT" \
    -d '{"amount":220000,"message":"Bawa alat sendiri, 3 jam","estimated_hours":3}')" "d['data']['id']")"
[[ -n "$BID" ]] && ok "penawaran diajukan ${DIM}${BID}${N}" || bad "penawaran gagal diajukan"

check "penawaran kedua dari orang lain" 201 "d['data']['amount']" '180000' \
    -X POST "$BASE/tasks/$TASK/bids" "${O[@]}" -H "$CT" -d '{"amount":180000}'
check "mengirim ulang MENGUBAH penawaran, bukan menambah" 200 "d['data']['amount']" '210000' \
    -X POST "$BASE/tasks/$TASK/bids" "${W[@]}" -H "$CT" -d '{"amount":210000}'
check "menawar task sendiri ditolak" 403 "d['code']" '"cannot_bid_own_task"' \
    -X POST "$BASE/tasks/$TASK/bids" "${AUTH[@]}" -H "$CT" -d '{"amount":200000}'
check "di bawah budget minimum ditolak" 422 "d['code']" '"bid_below_minimum"' \
    -X POST "$BASE/tasks/$TASK/bids" "${W[@]}" -H "$CT" -d '{"amount":100000}'
check "slot perekrutan terlihat di task" 200 \
    "[d['data']['hiring']['workers_needed'], d['data']['hiring']['workers_hired']]" '[1, 0]' \
    "${AUTH[@]}" "$BASE/tasks/$TASK"
check "pelamar ketiga tetap diterima — lelang tidak dibatasi slot" 201 "d['data']['amount']" '200000' \
    -X POST "$BASE/tasks/$TASK/bids" "${X[@]}" -H "$CT" -d '{"amount":200000}'
check "penerima kerja tidak boleh lihat daftar penawaran" 403 - - \
    "${W[@]}" "$BASE/tasks/$TASK/bids"
check "pemberi kerja melihat 3 penawaran untuk 1 slot" 200 "len(d['data'])" '3' \
    "${AUTH[@]}" "$BASE/tasks/$TASK/bids?sort=amount"
check "penawaran membawa sinyal kepercayaan penawar" 200 \
    "'identity_verified' in d['data'][0]['bidder']" 'true' \
    "${AUTH[@]}" "$BASE/tasks/$TASK/bids"

check "DEAL menetapkan agreed_amount dan menutup lelang" 200 \
    "[d['data']['status'], d['data']['agreed_amount']]" '["dealt", 210000]' \
    -X POST "$BASE/bids/$BID/accept" "${AUTH[@]}"
check "pelamar yang kalah ditutup, bukan dibiarkan menggantung" 200 "d['data'][0]['status']" '"rejected"' \
    "${O[@]}" "$BASE/bids/mine"
check "tagihan dibuat berstatus pending" 200 "d['data']['status']" '"pending"' \
    "${AUTH[@]}" "$BASE/tasks/$TASK/payment"
check "activity belum ada sebelum dana ditahan" 200 \
    "d['data'].get('activities') in (None, [])" 'true' \
    "${AUTH[@]}" "$BASE/tasks/$TASK"

# Pemberi kerja MELAPOR; yang menahan dana pengelola. Dua langkah, dua aktor.
PAY="$(json "$(curl -s "$BASE/tasks/$TASK/payment" "${AUTH[@]}")" "d['data']['id']")"
check "lapor transfer TIDAK membuka apa pun" 200 \
    "[d['data']['status'], d['data']['is_held'], d['data']['awaits_confirmation']]" \
    '["awaiting_confirmation", false, true]' \
    -X POST "$BASE/tasks/$TASK/payment/hold" "${AUTH[@]}"
check "activity masih belum ada setelah laporan" 200 \
    "d['data'].get('activities') in (None, [])" 'true' \
    "${AUTH[@]}" "$BASE/tasks/$TASK"
check "pemberi kerja tidak bisa mengonfirmasi transfernya sendiri" 401 "d['code']" '"unauthenticated"' \
    -X POST "$BASE/admin/payments/$PAY/confirm" "${AUTH[@]}"

check "pengelola konfirmasi -> dana ditahan -> task aktif" 200 \
    "[d['data']['status'], d['data']['is_held'], d['data']['task']['status']]" \
    '["held", true, "active"]' \
    -X POST "$BASE/admin/payments/$PAY/confirm" "${ADMIN[@]}"

ACT="$(json "$(curl -s "$BASE/activities/mine" "${W[@]}")" "d['data'][0]['id']")"
[[ -n "$ACT" ]] && ok "activity terbuka setelah konfirmasi ${DIM}${ACT}${N}" \
                || bad "activity tidak terbuka setelah konfirmasi"

check "konfirmasi dua kali ditolak" 422 "d['code']" '"invalid_status_transition"' \
    -X POST "$BASE/admin/payments/$PAY/confirm" "${ADMIN[@]}"
check "lapor lagi setelah dana ditahan ditolak" 422 "d['code']" '"invalid_status_transition"' \
    -X POST "$BASE/tasks/$TASK/payment/hold" "${AUTH[@]}"
check "penerima kerja mulai bekerja" 200 "d['data']['status']" '"in_progress"' \
    -X POST "$BASE/activities/$ACT/start" "${W[@]}"
check "serahkan hasil dengan bukti foto" 200 "len(d['data']['proof_photos'])" '2' \
    -X POST "$BASE/activities/$ACT/submit" "${W[@]}" -H "$CT" \
    -d '{"worker_note":"Sudah beres","proof_photos":["proof/a.jpg","proof/b.jpg"]}'
check "penerima kerja tidak boleh menyetujui sendiri" 403 - - \
    -X POST "$BASE/activities/$ACT/approve" "${W[@]}"
check "pemberi kerja setujui -> task selesai" 200 "d['data']['task']['status']" '"completed"' \
    -X POST "$BASE/activities/$ACT/approve" "${AUTH[@]}" -H "$CT" -d '{"poster_note":"Rapi"}'
check "dana dilepas" 200 "d['data']['status']" '"released"' \
    "${AUTH[@]}" "$BASE/tasks/$TASK/payment"

# ── 7b. satu task, banyak pekerja ────────────────────────────────────────────
echo
echo "${Y}==>${N} Satu task merekrut banyak pekerja"

reset_rate_limits
M1="$(json "$(signup 'Budi Kru' 'budi.kru@sekarya.test' '+628555111222')" "d['data']['access_token']")"
M2="$(json "$(signup 'Cici Kru' 'cici.kru@sekarya.test' '+628555111333')" "d['data']['access_token']")"
M3="$(json "$(signup 'Dedi Kru' 'dedi.kru@sekarya.test' '+628555111444')" "d['data']['access_token']")"
K1=(-H "Authorization: Bearer ${M1}" -H "$ACC")
K2=(-H "Authorization: Bearer ${M2}" -H "$ACC")
K3=(-H "Authorization: Bearer ${M3}" -H "$ACC")

CREW_JSON="$(curl -s -X POST "$BASE/tasks" "${AUTH[@]}" -H "$CT" -d '{
  "category_id":2,
  "title":"Bersih-bersih gudang sehari",
  "description":"Butuh beberapa orang untuk merapikan gudang dalam satu hari.",
  "budget_min":100000,
  "workers_needed":2,
  "publish_now":true
}')"
CREW="$(json "$CREW_JSON" "d['data']['id']")"
[[ -n "$CREW" ]] && ok "task 2 pekerja dibuat ${DIM}${CREW}${N}" \
                 || bad "task banyak pekerja gagal dibuat: ${CREW_JSON:0:200}"

B1="$(json "$(curl -s -X POST "$BASE/tasks/$CREW/bids" "${K1[@]}" -H "$CT" \
    -d '{"amount":120000}')" "d['data']['id']")"
B2="$(json "$(curl -s -X POST "$BASE/tasks/$CREW/bids" "${K2[@]}" -H "$CT" \
    -d '{"amount":150000}')" "d['data']['id']")"

B3="$(json "$(curl -s -X POST "$BASE/tasks/$CREW/bids" "${K3[@]}" -H "$CT" \
    -d '{"amount":110000}')" "d['data']['id']")"
check "pelamar boleh lebih banyak daripada slotnya" 200 "len(d['data'])" '3' \
    "${AUTH[@]}" "$BASE/tasks/$CREW/bids?sort=amount"
check "penawaran termurah di urutan pertama" 200 "d['data'][0]['amount']" '110000' \
    "${AUTH[@]}" "$BASE/tasks/$CREW/bids?sort=amount"

check "pekerja pertama mengisi satu slot" 200 \
    "[d['data']['status'], d['data']['hiring']['slots_remaining']]" '["open", 1]' \
    -X POST "$BASE/bids/$B1/accept" "${AUTH[@]}"
check "slot terakhir menutup lelang, tagihan = jumlah semua" 200 \
    "[d['data']['status'], d['data']['hiring']['workers_hired'], d['data']['agreed_amount']]" \
    '["dealt", 2, 270000]' \
    -X POST "$BASE/bids/$B2/accept" "${AUTH[@]}"
check "pelamar yang tidak terpilih ikut ditutup" 200 "d['data'][0]['status']" '"rejected"' \
    "${K3[@]}" "$BASE/bids/mine"
check "merekrut melebihi slot ditolak" 409 "d['code']" '"task_already_dealt"' \
    -X POST "$BASE/bids/$B3/accept" "${AUTH[@]}"

curl -s -o /dev/null -X POST "$BASE/tasks/$CREW/payment/hold" "${AUTH[@]}"
CREW_PAY="$(json "$(curl -s "$BASE/tasks/$CREW/payment" "${AUTH[@]}")" "d['data']['id']")"
check "satu konfirmasi menahan tagihan gabungan" 200 \
    "[d['data']['status'], d['data']['amount'], d['data']['task']['workers_hired']]" \
    '["held", 270000, 2]' \
    -X POST "$BASE/admin/payments/$CREW_PAY/confirm" "${ADMIN[@]}"
check "task menampilkan seluruh pekerjanya" 200 "len(d['data']['workers'])" '2' \
    "${AUTH[@]}" "$BASE/tasks/$CREW"

CA1="$(json "$(curl -s "$BASE/activities/mine" "${K1[@]}")" "d['data'][0]['id']")"
CA2="$(json "$(curl -s "$BASE/activities/mine" "${K2[@]}")" "d['data'][0]['id']")"

curl -s -o /dev/null -X POST "$BASE/activities/$CA1/start" "${K1[@]}"
check "penyerahan satu pekerja belum memindahkan task" 200 "d['data']['task']['status']" '"active"' \
    -X POST "$BASE/activities/$CA1/submit" "${K1[@]}" -H "$CT" -d '{"worker_note":"beres"}'
check "persetujuan pertama belum melepas dana" 200 "d['data']['status']" '"held"' \
    "${AUTH[@]}" "$BASE/tasks/$CREW/payment"

curl -s -o /dev/null -X POST "$BASE/activities/$CA1/approve" "${AUTH[@]}"
check "dana masih ditahan sampai pekerja terakhir disetujui" 200 "d['data']['status']" '"held"' \
    "${AUTH[@]}" "$BASE/tasks/$CREW/payment"

curl -s -o /dev/null -X POST "$BASE/activities/$CA2/start" "${K2[@]}"
curl -s -o /dev/null -X POST "$BASE/activities/$CA2/submit" "${K2[@]}" -H "$CT" -d '{"worker_note":"beres"}'
check "pekerja terakhir disetujui -> task selesai" 200 "d['data']['task']['status']" '"completed"' \
    -X POST "$BASE/activities/$CA2/approve" "${AUTH[@]}"
check "dana dilepas sekali, sebesar seluruh tagihan" 200 \
    "[d['data']['status'], d['data']['amount']]" '["released", 270000]' \
    "${AUTH[@]}" "$BASE/tasks/$CREW/payment"

check "pemberi kerja menilai pekerja tertentu" 201 "d['data']['reviewer_role']" '"poster"' \
    -X POST "$BASE/tasks/$CREW/reviews" "${AUTH[@]}" -H "$CT" \
    -d "{\"rating\":5,\"worker_id\":\"$(json "$(curl -s "$BASE/tasks/$CREW" "${AUTH[@]}")" "d['data']['workers'][0]['id']")\"}"
check "menilai tanpa menyebut pekerja ditolak" 422 "d['code']" '"review_target_required"' \
    -X POST "$BASE/tasks/$CREW/reviews" "${AUTH[@]}" -H "$CT" -d '{"rating":4}'

# ── 7c. mulai lebih awal dengan pekerja seadanya ─────────────────────────────
echo
echo "${Y}==>${N} Mulai lebih awal: target dikunci ke pekerja yang sudah diterima"

SHORT_JSON="$(curl -s -X POST "$BASE/tasks" "${AUTH[@]}" -H "$CT" -d '{
  "category_id":2,
  "title":"Angkut barang pindahan",
  "description":"Butuh 5 orang, tapi tanggalnya tidak bisa mundur.",
  "budget_min":100000,
  "workers_needed":5,
  "publish_now":true
}')"
SHORT="$(json "$SHORT_JSON" "d['data']['id']")"

SB1="$(json "$(curl -s -X POST "$BASE/tasks/$SHORT/bids" "${K1[@]}" -H "$CT" \
    -d '{"amount":130000}')" "d['data']['id']")"
curl -s -o /dev/null -X POST "$BASE/tasks/$SHORT/bids" "${K2[@]}" -H "$CT" -d '{"amount":140000}'

check "menerima satu dari lima slot, lelang masih terbuka" 200 \
    "[d['data']['status'], d['data']['hiring']['slots_remaining']]" '["open", 4]' \
    -X POST "$BASE/bids/$SB1/accept" "${AUTH[@]}"
check "mulai lebih awal: target turun ke yang sudah diterima" 200 \
    "[d['data']['status'], d['data']['hiring']['workers_needed'], d['data']['hiring']['slots_remaining']]" \
    '["dealt", 1, 0]' \
    -X POST "$BASE/tasks/$SHORT/start" "${AUTH[@]}"
check "pelamar yang menunggu ditutup saat berhenti lebih awal" 200 \
    "d['data'][0]['status']" '"rejected"' \
    "${K2[@]}" "$BASE/bids/mine"
check "mulai lagi ditolak — task tidak lagi open" 422 "d['code']" '"task_not_biddable"' \
    -X POST "$BASE/tasks/$SHORT/start" "${AUTH[@]}"

# ── 8. penilaian ─────────────────────────────────────────────────────────────
echo
echo "${Y}==>${N} Penilaian dua arah"
check "pemberi kerja menilai penerima kerja" 201 "d['data']['reviewer_role']" '"poster"' \
    -X POST "$BASE/tasks/$TASK/reviews" "${AUTH[@]}" -H "$CT" -d '{"rating":5,"comment":"Bagus"}'
check "penerima kerja menilai pemberi kerja" 201 "d['data']['reviewer_role']" '"worker"' \
    -X POST "$BASE/tasks/$TASK/reviews" "${W[@]}" -H "$CT" -d '{"rating":4}'
check "menilai dua kali ditolak" 422 "d['code']" '"review_not_allowed"' \
    -X POST "$BASE/tasks/$TASK/reviews" "${AUTH[@]}" -H "$CT" -d '{"rating":1}'
# float(): JSON menserialisasi 5.0 sebagai 5, jadi perbandingannya harus numerik.
check "agregat dipisah per peran" 200 \
    "[float(d['data']['as_worker']['rating_avg']), float(d['data']['as_poster']['rating_avg'])]" '[5.0, 0.0]' \
    "${W[@]}" "$BASE/me"

# ── 9. batas pengungkapan data ───────────────────────────────────────────────
echo
echo "${Y}==>${N} Batas pengungkapan data"
curl -s -X POST "$BASE/me/verifications" "${W[@]}" -H "$CT" -d '{
  "type":"identity",
  "id_card_photo_path":"verifications/ktp-rahasia.jpg",
  "selfie_photo_path":"verifications/selfie-rahasia.jpg",
  "document_number":"3174012345678901",
  "name_on_document":"Siti Penerima"
}' -o /dev/null

check "verifikasi hanya mengembalikan status" 200 "d['data'][0]['has_id_card_photo']" 'true' \
    "${W[@]}" "$BASE/me/verifications"
check "path foto TIDAK bocor" 200 "'ktp-rahasia' in json.dumps(d)" 'false' \
    "${W[@]}" "$BASE/me/verifications"
check "NIK TIDAK bocor" 200 "'3174012345678901' in json.dumps(d)" 'false' \
    "${W[@]}" "$BASE/me/verifications"
check "hash kata sandi TIDAK bocor" 200 "'password' in d['data']" 'false' \
    "${W[@]}" "$BASE/me"

# ── 9b. antrean pengelola ────────────────────────────────────────────────────
echo
echo "${Y}==>${N} Verifikasi & moderasi oleh pengelola"

VER_ID="$(json "$(curl -s "$BASE/admin/verifications" "${ADMIN[@]}")" "d['data'][0]['id']")"
check "pengajuan muncul di antrean pengelola" 200 \
    "[d['data'][0]['status'], d['data'][0]['awaits_review']]" '["pending", true]' \
    "${ADMIN[@]}" "$BASE/admin/verifications"
check "DAFTAR antrean tidak membawa NIK" 200 "'3174012345678901' in json.dumps(d)" 'false' \
    "${ADMIN[@]}" "$BASE/admin/verifications"
check "DETAIL membuka NIK untuk penilainya" 200 "d['data']['document_number']" '"3174012345678901"' \
    "${ADMIN[@]}" "$BASE/admin/verifications/$VER_ID"
check "detail tetap menyembunyikan path foto" 200 "'ktp-rahasia' in json.dumps(d)" 'false' \
    "${ADMIN[@]}" "$BASE/admin/verifications/$VER_ID"
check "menolak tanpa alasan ditolak validasi" 422 "'reason' in d['errors']" 'true' \
    -X POST "$BASE/admin/verifications/$VER_ID/reject" "${ADMIN[@]}" -H "$CT" -d '{}'
check "pengelola menyetujui identitas" 200 "d['data']['status']" '"verified"' \
    -X POST "$BASE/admin/verifications/$VER_ID/approve" "${ADMIN[@]}"
check "badge terverifikasi menyala untuk pemiliknya" 200 "d['data'][0]['is_verified']" 'true' \
    "${W[@]}" "$BASE/me/verifications"
check "menyetujui dua kali ditolak" 422 "d['code']" '"invalid_status_transition"' \
    -X POST "$BASE/admin/verifications/$VER_ID/approve" "${ADMIN[@]}"
check "pengguna tidak bisa menyentuh antrean verifikasi" 401 "d['code']" '"unauthenticated"' \
    -X POST "$BASE/admin/verifications/$VER_ID/approve" "${W[@]}"

# Moderasi: yang benar-benar menghentikan orangnya adalah pencabutan token,
# bukan kolom status — token akses hidup delapan jam dan tidak menyimpannya.
#
# Sasarannya akun baru yang SUDAH memverifikasi email. Memakai akun yang belum
# verifikasi akan menguji hal lain: login-nya ditolak `email_not_verified`
# lebih dulu, jadi penangguhannya tidak pernah terbukti berpengaruh.
MOD_PAIR="$(signup "Akun Moderasi" "moderasi@sekarya.test" "+628777000111")"
MOD="$(json "$MOD_PAIR" "d['data']['user']['id']")"
MOD_TOKEN="$(json "$MOD_PAIR" "d['data']['access_token']")"

check "cari pengguna lewat email persis" 200 "len(d['data'])" '1' \
    "${ADMIN[@]}" "$BASE/admin/users?email=moderasi@sekarya.test"
check "email tak terdaftar = daftar kosong, bukan 422" 200 "len(d['data'])" '0' \
    "${ADMIN[@]}" "$BASE/admin/users?email=hantu@sekarya.test"
check "token akun itu masih berlaku sebelum moderasi" 200 "d['data']['status']" '"active"' \
    -H "Authorization: Bearer ${MOD_TOKEN}" -H "$ACC" "$BASE/me"
check "menangguhkan butuh alasan" 422 "'reason' in d['errors']" 'true' \
    -X POST "$BASE/admin/users/$MOD/suspend" "${ADMIN[@]}" -H "$CT" -d '{}'
check "pengguna ditangguhkan" 200 "d['data']['status']" '"suspended"' \
    -X POST "$BASE/admin/users/$MOD/suspend" "${ADMIN[@]}" -H "$CT" \
    -d '{"reason":"Melaporkan transfer palsu dua kali berturut-turut."}'
check "token yang SUDAH DIPEGANG langsung mati" 401 "d['code']" '"unauthenticated"' \
    -H "Authorization: Bearer ${MOD_TOKEN}" -H "$ACC" "$BASE/me"
check "akun yang ditangguhkan tidak bisa masuk lagi" 403 "d['code']" '"account_not_active"' \
    -X POST "$BASE/auth/login" -H "$ACC" -H "$CT" \
    -d '{"email":"moderasi@sekarya.test","password":"RahasiaKuat2026"}'
check "pengguna dipulihkan ke active (emailnya sudah terverifikasi)" 200 "d['data']['status']" '"active"' \
    -X POST "$BASE/admin/users/$MOD/reinstate" "${ADMIN[@]}"
check "dan bisa masuk lagi" 200 "d['data']['user']['status']" '"active"' \
    -X POST "$BASE/auth/login" -H "$ACC" -H "$CT" \
    -d '{"email":"moderasi@sekarya.test","password":"RahasiaKuat2026"}'

# Akun pengelola: hanya super_admin.
check "peran admin TIDAK boleh melihat daftar pengelola" 403 "d['context']['reason']" '"insufficient_role"' \
    "${VERIF_ADMIN[@]}" "$BASE/admin/admins"
check "peran admin TIDAK boleh membuat pengelola" 403 "d['context']['reason']" '"insufficient_role"' \
    -X POST "$BASE/admin/admins" "${VERIF_ADMIN[@]}" -H "$CT" \
    -d '{"name":"Curang","email":"curang@sekarya.test","password":"RahasiaKuatSekali99!","password_confirmation":"RahasiaKuatSekali99!"}'
check "super_admin membuat pengelola baru" 201 "[d['data']['role'], d['data']['status']]" '["admin", "active"]' \
    -X POST "$BASE/admin/admins" "${ADMIN[@]}" -H "$CT" \
    -d '{"name":"Verifikator Tiga","email":"verif3@sekarya.test","password":"RahasiaKuatSekali99!","password_confirmation":"RahasiaKuatSekali99!"}'
check "peran TIDAK bisa diselundupkan lewat payload" 201 "d['data']['role']" '"admin"' \
    -X POST "$BASE/admin/admins" "${ADMIN[@]}" -H "$CT" \
    -d '{"name":"Penyusup","email":"penyusup@sekarya.test","password":"RahasiaKuatSekali99!","password_confirmation":"RahasiaKuatSekali99!","role":"super_admin"}'

SUPER_ID="$(json "$(curl -s "$BASE/admin/me" "${ADMIN[@]}")" "d['data']['id']")"
NEW_ADMIN_ID="$(json "$(curl -s "$BASE/admin/admins" "${ADMIN[@]}")" \
    "[a['id'] for a in d['data'] if a['email'] == 'verif3@sekarya.test'][0]")"
check "super_admin TIDAK bisa dihapus" 403 "d['code']" '"super_admin_protected"' \
    -X DELETE "$BASE/admin/admins/$SUPER_ID" "${ADMIN[@]}"
check "pengelola biasa bisa dihapus" 200 - - \
    -X DELETE "$BASE/admin/admins/$NEW_ADMIN_ID" "${ADMIN[@]}"
check "yang dihapus tidak muncul lagi di daftar" 200 \
    "[a['email'] for a in d['data'] if a['email'] == 'verif3@sekarya.test']" '[]' \
    "${ADMIN[@]}" "$BASE/admin/admins"

# ── 10. feed & filter ────────────────────────────────────────────────────────
echo
echo "${Y}==>${N} Feed pencari kerja & filter"

curl -s -X POST "$BASE/tasks" "${AUTH[@]}" -H "$CT" -d '{
  "category_id":2,"title":"Bersihkan kamar mandi","description":"Kamar mandi berkerak, disikat bersih.",
  "budget_min":120000,"city":"Jakarta","latitude":-6.2088,"longitude":106.8456,
  "skills":["bersih-kamar-mandi"],"publish_now":true}' -o /dev/null
curl -s -X POST "$BASE/tasks" "${AUTH[@]}" -H "$CT" -d '{
  "category_id":3,"title":"Jaga kucing 3 hari","description":"Titip 2 kucing persia, beri makan pagi sore.",
  "budget_min":200000,"city":"Bandung","latitude":-6.9175,"longitude":107.6191,
  "skills":["jaga-kucing"],"publish_now":true}' -o /dev/null
# Judul ini yang menguji dua kelemahan FULLTEXT: kata dua huruf ("AC") dan
# bentuk berimbuhan ("Membersihkan" dicari dengan "bersih").
curl -s -X POST "$BASE/tasks" "${AUTH[@]}" -H "$CT" -d '{
  "category_id":2,"title":"Membersihkan AC ruang kerja","description":"Unit split, freon dicek.",
  "budget_min":180000,"city":"Jakarta","publish_now":true}' -o /dev/null

check "feed tidak memuat task sendiri" 200 "len(d['data'])" '0' \
    "${AUTH[@]}" "$BASE/tasks"
check "pencari kerja melihat task orang lain" 200 "len(d['data'])" '3' \
    "${W[@]}" "$BASE/tasks"
check "task yang sudah dilamar ditandai my_bid" 200 \
    "any(t.get('my_bid') for t in d['data'])" 'false' \
    "${W[@]}" "$BASE/tasks"
check "kata kunci lewat FULLTEXT" 200 "len(d['data'])" '1' \
    -G "${W[@]}" "$BASE/tasks" --data-urlencode 'q=kamar mandi'
check "kata kunci di deskripsi" 200 "len(d['data'])" '1' \
    -G "${W[@]}" "$BASE/tasks" --data-urlencode 'q=persia'
check "kata kunci hanya tanda baca -> nol baris" 200 "len(d['data'])" '0' \
    -G "${W[@]}" "$BASE/tasks" --data-urlencode 'q=!!!'
check "nama dua huruf tetap ketemu" 200 "d['data'][0]['title']" '"Membersihkan AC ruang kerja"' \
    -G "${W[@]}" "$BASE/tasks" --data-urlencode 'q=ac'
check "akar kata menemukan judul berimbuhan" 200 "d['data'][0]['title']" '"Membersihkan AC ruang kerja"' \
    -G "${W[@]}" "$BASE/tasks" --data-urlencode 'q=bersih'
check "mengetik separuh kata terakhir sudah menemukan" 200 "len(d['data'])" '1' \
    -G "${W[@]}" "$BASE/tasks" --data-urlencode 'q=jaga kuc'
# Yang diuji: MySQL tidak melempar galat sintaks. Karakter `+ - ( ) "` punya
# arti di BOOLEAN MODE, dan masukan mentah yang lolos ke sana menghasilkan 500,
# bukan nol hasil. Jumlah barisnya sendiri tidak relevan di sini.
check "karakter operator tidak merusak kueri" 200 - - \
    -G "${W[@]}" "$BASE/tasks" --data-urlencode 'q=jaga -"kucing" +(besar)'
check "filter waktu: 24 jam terakhir memuat semuanya" 200 "len(d['data'])" '3' \
    -G "${W[@]}" "$BASE/tasks" -d 'posted_within_hours=24'
check "filter waktu di luar rentang ditolak" 422 - - \
    -G "${W[@]}" "$BASE/tasks" -d 'posted_within_hours=721'
check "radius 5 km menyaring Bandung" 200 "len(d['data'])" '1' \
    -G "${W[@]}" "$BASE/tasks" -d 'lat=-6.1754' -d 'lng=106.8272' -d 'radius_km=5'
check "jarak dilaporkan" 200 "d['data'][0]['distance_km'] is not None" 'true' \
    -G "${W[@]}" "$BASE/tasks" -d 'lat=-6.1754' -d 'lng=106.8272' -d 'radius_km=5'
check "lat tanpa lng ditolak" 422 "list(d['errors'].keys())" '["lng"]' \
    -G "${W[@]}" "$BASE/tasks" -d 'lat=-6.1754'
check "filter skills" 200 "d['data'][0]['skills'][0]['slug']" '"jaga-kucing"' \
    -G "${W[@]}" "$BASE/tasks" -d 'skills=jaga-kucing'
check "match_my_skills tanpa skill -> kosong" 200 "len(d['data'])" '0' \
    -G "${O[@]}" "$BASE/tasks" -d 'match_my_skills=1'

# ── 11. cursor pagination ────────────────────────────────────────────────────
echo
echo "${Y}==>${N} Cursor pagination"
check "meta punya next_cursor" 200 "'next_cursor' in d['meta']" 'true' \
    -G "${W[@]}" "$BASE/tasks" -d 'per_page=1'
check "meta TIDAK punya current_page" 200 "'current_page' in d['meta']" 'false' \
    -G "${W[@]}" "$BASE/tasks" -d 'per_page=1'
check "meta TIDAK punya total" 200 "'total' in d['meta']" 'false' \
    -G "${W[@]}" "$BASE/tasks" -d 'per_page=1'
check "?page diabaikan" 200 "d['meta']['per_page']" '1' \
    -G "${W[@]}" "$BASE/tasks" -d 'per_page=1' -d 'page=2'
check "per_page di atas maksimum ditolak" 422 "list(d['errors'].keys())" '["per_page"]' \
    -G "${W[@]}" "$BASE/tasks" -d 'per_page=5000'

# ── 12. logout ───────────────────────────────────────────────────────────────
echo
echo "${Y}==>${N} Logout mencabut semuanya"
check "logout berhasil" 200 - - -X POST "${AUTH[@]}" "$BASE/auth/logout"
check "access token mati setelah logout" 401 "d['code']" '"unauthenticated"' \
    "${AUTH[@]}" "$BASE/me"
check "long_lived juga dicabut" 401 "d['code']" '"unauthenticated"' \
    -X POST "${AUTH_LONG[@]}" "$BASE/auth/refresh"

# ── ringkasan ────────────────────────────────────────────────────────────────
echo
TOTAL=$((PASS + FAIL))
if [[ "$FAIL" == "0" ]]; then
    echo "${G}SEMUA LULUS${N}  ${PASS}/${TOTAL} pemeriksaan"
    exit 0
fi
echo "${R}GAGAL${N}  ${FAIL} dari ${TOTAL} pemeriksaan"
exit 1
