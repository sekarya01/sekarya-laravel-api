@extends('super_admin.layout')

@section('title', 'Pekerja #' . $worker->id)
@section('header', 'Detail Pekerja')

@section('content')
@php
    $u = $worker->user;
    $waddr = $worker->resolvedAddress();
@endphp

<div class="anim-rise">
    <a href="{{ route('super_admin.workers.index') }}" class="btn btn-ghost py-2! text-xs!">← Kembali ke direktori</a>
</div>

<div class="anim-rise ad-1 mt-3 card p-5 sm:p-6 flex flex-wrap items-center gap-4">
    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-xl font-extrabold text-white shrink-0" style="background: linear-gradient(135deg, #F97316, #C2570B);">
        {{ strtoupper(substr($worker->resolvedName() ?? '?', 0, 1)) }}
    </div>
    <div class="min-w-0 flex-1">
        <h3 class="text-xl font-extrabold truncate" style="color: #163C68;">{{ $worker->resolvedName() ?? '—' }}</h3>
        <p class="text-sm text-slate-500">{{ $u?->gender?->label() ?? '—' }} · {{ $worker->age() !== null ? $worker->age() . ' thn' : '—' }} · {{ $waddr['city'] ?? '—' }}</p>
    </div>
    @include('super_admin.partials.badge', ['text' => 'profil terdaftar', 'tone' => 'green'])
    @if ($u?->readyToWork())
        @include('super_admin.partials.badge', ['text' => 'siap kerja', 'tone' => 'orange'])
    @endif
</div>

<div class="anim-rise ad-1 mt-4 grid sm:grid-cols-2 gap-2 text-sm">
    <div class="flex items-center gap-2 rounded-xl px-3.5 py-2.5 bg-emerald-50 border border-emerald-100">
        <span class="font-bold text-emerald-700">✓</span>
        <span>Profil pekerja terdaftar</span>
    </div>
    @if ($u?->isIdentityVerified())
        <div class="flex items-center gap-2 rounded-xl px-3.5 py-2.5 bg-emerald-50 border border-emerald-100">
            <span class="font-bold text-emerald-700">✓</span>
            <span>Identitas diverifikasi pengelola</span>
        </div>
    @else
        <div class="flex items-center gap-2 rounded-xl px-3.5 py-2.5 bg-slate-50 border border-slate-100">
            <span class="font-bold text-slate-400">○</span>
            <span class="text-slate-500">Identitas belum diverifikasi — belum siap kerja</span>
        </div>
    @endif
</div>
@if ($u && ! $u->hasCompleteIdentity())
    <p class="anim-rise mt-3 text-xs leading-relaxed rounded-xl p-3" style="background: #FFF1E4; border: 1px solid #FBD9B6; color: #9A4A0B;">
        Identitas akun belum lengkap ({{ $u->gender === null ? 'jenis kelamin' : '' }}{{ $u->gender === null && $u->birth_date === null ? ' + ' : '' }}{{ $u->birth_date === null ? 'tanggal lahir' : '' }} kosong) —
        pengajuan profil pekerja (<span class="font-mono font-bold">PUT /me/worker</span>) akan ditolak <span class="font-mono font-bold">profile_incomplete</span>.
    </p>
@endif

@php
    $istatus = $identity?->status->value;
    $itone = match($istatus) {
        'verified' => 'green', 'rejected' => 'red', 'revoked' => 'slate',
        'in_review' => 'navy', default => 'amber',
    };
@endphp
<div class="anim-rise ad-1 mt-4 card p-5 sm:p-6" style="border-top: 4px solid #F97316;">
    <div class="flex flex-wrap items-center gap-3">
        <h4 class="font-extrabold" style="color: #163C68;">Verifikasi identitas — syarat menjadi pekerja</h4>
        @if ($identity)
            @include('super_admin.partials.badge', ['text' => $istatus, 'tone' => $itone])
        @else
            @include('super_admin.partials.badge', ['text' => 'belum mengajukan', 'tone' => 'slate'])
        @endif
        @if ($identity)
            <a href="{{ route('super_admin.verifications.show', $identity) }}" class="ml-auto text-xs font-bold hover:underline" style="color: #F97316;">Halaman verifikasi →</a>
        @endif
    </div>

    @if ($identity === null)
        <p class="mt-2 text-sm text-slate-500">Orang ini belum mengajukan verifikasi identitas. Tanpa persetujuan pengelola, penanda siap kerja tidak akan pernah menyala.</p>
    @elseif ($identity->status->awaitsReview())
        <p class="mt-2 text-xs rounded-xl px-3 py-2" style="background: #FFFBEB; border: 1px solid #F3DFAE; color: #92400E;">Membuka halaman ini mencatat <span class="font-mono font-bold">verification.viewed</span> — NIK di bawah hanya ada di sini dan di halaman verifikasi.</p>
        @php
            $docBirth = $identity->birth_date_on_document?->toDateString();
            $accBirth = $u?->birth_date?->toDateString();
        @endphp
        <div class="mt-3 rounded-2xl p-4" style="background: linear-gradient(135deg, #0A1E35, #163C68);">
            <p class="text-xs uppercase tracking-widest font-bold text-orange-300">NIK KTP</p>
            <p class="mt-1 font-mono text-xl sm:text-2xl font-bold tracking-wider text-white">{{ $identity->document_number_enc ?? '—' }}</p>
            <p class="mt-2 text-sm text-slate-300">{{ $identity->name_on_document ?? '—' }} · lahir {{ $docBirth ?? '—' }}</p>
            @if ($docBirth && $accBirth)
                <p class="mt-2">@include('super_admin.partials.badge', ['text' => $docBirth === $accBirth ? 'tgl lahir cocok dengan akun' : 'BEDA — periksa KTP', 'tone' => $docBirth === $accBirth ? 'green' : 'red'])</p>
            @endif
        </div>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <form method="POST" action="{{ route('super_admin.verifications.approve', $identity) }}">
                @csrf
                <input type="hidden" name="redirect_to" value="/{{ request()->path() }}">
                <button class="btn btn-success w-full">✓ Setujui — nyalakan siap kerja</button>
            </form>
            <form method="POST" action="{{ route('super_admin.verifications.reject', $identity) }}" class="flex flex-col gap-2">
                @csrf
                <input type="hidden" name="redirect_to" value="/{{ request()->path() }}">
                <textarea name="reason" required minlength="10" maxlength="500" rows="2" class="field" placeholder="Alasan penolakan (dibaca penggunanya)...">{{ old('reason') }}</textarea>
                <button class="btn btn-danger w-full">Tolak</button>
            </form>
        </div>
    @elseif ($istatus === 'verified')
        <p class="mt-2 text-sm text-slate-500">Disetujui {{ $identity->reviewed_at ?? '' }}. Penanda siap kerja menyala selama profilnya ada.</p>
        <form method="POST" action="{{ route('super_admin.verifications.revoke', $identity) }}" class="mt-3 flex flex-col sm:flex-row gap-2">
            @csrf
            <input type="hidden" name="redirect_to" value="/{{ request()->path() }}">
            <input name="reason" required minlength="10" maxlength="500" class="field flex-1" placeholder="Alasan pencabutan (mis. terbukti palsu)...">
            <button class="btn btn-navy shrink-0">Cabut</button>
        </form>
    @else
        <p class="mt-2 text-sm text-slate-500">
            Status <span class="font-mono font-bold">{{ $istatus }}</span>
            @if ($identity->rejection_reason)— alasan: “{{ $identity->rejection_reason }}”@endif
            @if ($identity->revoked_reason)— alasan: “{{ $identity->revoked_reason }}”@endif
            · <a href="{{ route('super_admin.verifications.show', $identity) }}" class="font-bold hover:underline" style="color: #F97316;">lihat riwayatnya →</a>
        </p>
    @endif
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="anim-rise ad-1 card p-5 sm:p-6">
        <h4 class="font-extrabold" style="color: #163C68;">Identitas & kontak terpakai</h4>
        <p class="text-xs text-slate-400">NULL berarti warisan dari akun — bukan data hilang.</p>
        <dl class="mt-3 space-y-2.5 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-slate-400 shrink-0">Nama tampil</dt><dd class="font-medium text-right">{{ $worker->resolvedName() ?? '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-400 shrink-0">Kontak</dt><dd class="font-medium text-right">{{ $worker->resolvedPhone() ?? '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-400 shrink-0">Jenis kelamin</dt><dd class="font-medium text-right">{{ $worker->gender()?->label() ?? '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-400 shrink-0">Umur</dt><dd class="font-medium text-right">{{ $worker->age() !== null ? $worker->age() . ' tahun' : '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-400 shrink-0">Alamat kerja</dt><dd class="font-medium text-right">{{ $waddr['address_line'] ?? '—' }}, {{ $waddr['city'] ?? '—' }}{{ $waddr['province'] ? ', ' . $waddr['province'] : '' }}{{ $waddr['postal_code'] ? ' ' . $waddr['postal_code'] : '' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-400 shrink-0">Sumber alamat</dt><dd class="text-right">@include('super_admin.partials.badge', ['text' => $worker->hasOwnAddress() ? 'diisi sendiri' : 'warisan akun', 'tone' => $worker->hasOwnAddress() ? 'navy' : 'slate'])</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-400 shrink-0">Radius kerja</dt><dd class="font-medium text-right">{{ $worker->radius_km !== null ? $worker->radius_km . ' km' : '—' }}</dd></div>
            @if ($worker->latitude !== null && $worker->longitude !== null)
                <div class="flex justify-between gap-3"><dt class="text-slate-400 shrink-0">Titik kerja</dt><dd class="font-mono text-xs text-right">{{ $worker->latitude }}, {{ $worker->longitude }}</dd></div>
            @endif
            <div class="flex justify-between gap-3"><dt class="text-slate-400 shrink-0">Profil sejak</dt><dd class="text-right text-slate-500 text-xs">{{ $worker->created_at }}</dd></div>
        </dl>
    </div>

    <div class="anim-rise ad-2 card p-5 sm:p-6">
        <h4 class="font-extrabold" style="color: #163C68;">Reputasi & keahlian</h4>
        <p class="text-xs text-slate-400">Hanya Action yang menulis angka ini — tidak pernah dari payload.</p>
        <div class="mt-3 grid grid-cols-2 gap-3 text-center">
            <div class="rounded-xl bg-slate-50 border border-slate-100 p-3"><p class="text-2xl font-extrabold" style="color: #163C68;">{{ number_format((float) $worker->worker_rating_avg, 1) }}</p><p class="text-xs text-slate-400">rating ({{ $worker->worker_rating_count }})</p></div>
            <div class="rounded-xl bg-slate-50 border border-slate-100 p-3"><p class="text-2xl font-extrabold" style="color: #163C68;">{{ $worker->tasks_completed }}</p><p class="text-xs text-slate-400">task selesai</p></div>
            <div class="rounded-xl bg-slate-50 border border-slate-100 p-3 col-span-2"><p class="text-2xl font-extrabold" style="color: #F97316;">{{ $worker->bids_won }}</p><p class="text-xs text-slate-400">penawaran dimenangkan</p></div>
        </div>
        @if ($u && $u->skills->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($u->skills as $skill)
                    @include('super_admin.partials.badge', ['text' => $skill->name, 'tone' => 'navy'])
                @endforeach
            </div>
        @else
            <p class="mt-3 text-xs text-slate-400">Belum ada keahlian terdaftar.</p>
        @endif
    </div>
</div>

@if ($u)
    <a href="{{ route('super_admin.users.show', $u->ulid) }}" class="anim-rise ad-2 mt-4 card card-lift p-5 flex flex-wrap items-center gap-4">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white font-extrabold shrink-0" style="background: linear-gradient(135deg, #163C68, #2A5E9E);">
            {{ strtoupper(substr($u->name, 0, 1)) }}
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-extrabold" style="color: #163C68;">Akun pemilik: {{ $u->name }}</p>
            <p class="text-xs text-slate-500 truncate">{{ $u->email }} · <span class="font-mono">{{ $u->status->value }}</span> — moderasi & riwayat verifikasi ada di sana.</p>
        </div>
        <span class="text-sm font-bold" style="color: #F97316;">Buka akun →</span>
    </a>
@endif
@endsection
