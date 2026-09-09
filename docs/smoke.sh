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

# Daftar + verifikasi lewat alur nyata; kode dibaca dari log email.
# Menulis respons pasangan token ke stdout.
signup() {
    : > "$LOG"
    curl -s -X POST "$BASE/auth/register" -H "$ACC" -H "$CT" \
        -d "{\"name\":\"$1\",\"email\":\"$2\",\"phone\":\"$3\",\"password\":\"RahasiaKuat2026\",\"password_confirmation\":\"RahasiaKuat2026\",\"city\":\"Jakarta\"}" \
        -o /dev/null
    local code
    code="$(grep -oE '\*\*[0-9]{6}\*\*' "$LOG" | head -1 | tr -d '*')"
    curl -s -X POST "$BASE/auth/verify-email" -H "$ACC" -H "$CT" \
        -d "{\"email\":\"$2\",\"code\":\"$code\"}"
}

# ── 1. reset ─────────────────────────────────────────────────────────────────
echo "${Y}==>${N} Reset database (data dev hilang)"
if ! php artisan migrate:fresh --seed --no-interaction >/dev/null 2>&1; then
    echo "${R}FATAL${N} migrate:fresh gagal. Cek koneksi MySQL dan DB_DATABASE di .env."
    exit 1
fi
grep -q '^MAIL_MAILER=log' .env || \
    echo "${Y}CATATAN${N} MAIL_MAILER bukan 'log' — kode verifikasi tidak terbaca dari log."

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

# Satu transfer membuka satu activity PER pekerja, jadi responsnya daftar.
ACT="$(json "$(curl -s -X POST "$BASE/tasks/$TASK/payment/hold" "${AUTH[@]}")" "d['data'][0]['id']")"
[[ -n "$ACT" ]] && ok "transfer -> dana ditahan -> activity dibuka ${DIM}${ACT}${N}" \
                || bad "activity tidak terbuka setelah transfer"

check "transfer dua kali ditolak" 422 "d['code']" '"invalid_status_transition"' \
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

check "satu transfer membuka satu activity per pekerja" 201 \
    "[len(d['data']), sorted(a['agreed_amount'] for a in d['data'])]" '[2, [120000, 150000]]' \
    -X POST "$BASE/tasks/$CREW/payment/hold" "${AUTH[@]}"
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
