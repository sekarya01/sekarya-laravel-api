@extends('super_admin.layout')

@section('title', $user->email)
@section('header', 'Profil Pengguna')

@section('content')
<div class="anim-rise">
    <a href="{{ route('super_admin.users.index') }}" class="btn btn-ghost !py-2 !text-xs">← Kembali ke daftar</a>
</div>

<div class="anim-rise ad-1 mt-3 card p-5 sm:p-6 flex flex-wrap items-center gap-4">
    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-xl font-extrabold text-white shrink-0" style="background: linear-gradient(135deg, #163C68, #2A5E9E);">
        {{ strtoupper(substr($user->name, 0, 1)) }}
    </div>
    <div class="min-w-0 flex-1">
        <h3 class="text-xl font-extrabold truncate" style="color: #163C68;">{{ $user->name }}</h3>
        <p class="text-sm text-slate-500 truncate">{{ $user->email }}</p>
    </div>
    @php
        $tone = match($user->status->value) {
            'active' => 'green', 'banned' => 'red', 'suspended' => 'orange', default => 'amber',
        };
    @endphp
    @include('super_admin.partials.badge', ['text' => $user->status->value, 'tone' => $tone])
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="anim-rise ad-1 card p-5 sm:p-6">
        <h4 class="font-extrabold" style="color: #163C68;">Profil</h4>
        <dl class="mt-3 space-y-2.5 text-sm">
            @foreach ([['ULID', $user->ulid, true], ['Telepon', $user->phone ?? '—', false], ['Kota', $user->city ?? '—', false], ['Email terverifikasi', (string) ($user->email_verified_at ?? 'belum'), false], ['Terdaftar', (string) $user->created_at, false]] as [$k, $val, $mono])
                <div class="flex justify-between gap-3"><dt class="text-slate-400 shrink-0">{{ $k }}</dt><dd class="font-medium text-right break-all @if($mono) font-mono text-xs @endif">{{ $val }}</dd></div>
            @endforeach
        </dl>
    </div>
    <div class="anim-rise ad-2 card p-5 sm:p-6">
        <h4 class="font-extrabold" style="color: #163C68;">Riwayat verifikasi</h4>
        <div class="mt-3 space-y-2">
            @forelse ($user->verifications as $v)
                @php
                    $vt = match($v->status->value) {
                        'verified' => 'green', 'rejected' => 'red', 'revoked' => 'slate',
                        'in_review' => 'navy', default => 'amber',
                    };
                @endphp
                <div class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 border border-slate-100 px-3.5 py-2.5 text-sm">
                    <span class="font-medium">{{ $v->type->value }}</span>
                    <span class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">{{ $v->submitted_at }}</span>
                        @include('super_admin.partials.badge', ['text' => $v->status->value, 'tone' => $vt])
                    </span>
                </div>
            @empty
                @include('super_admin.partials.empty', ['title' => 'Belum pernah mengajukan', 'hint' => 'Pengguna ini belum mengajukan verifikasi identitas.'])
            @endforelse
        </div>
    </div>
</div>

<div class="mt-4 grid gap-4 md:grid-cols-3">
    <form method="POST" action="{{ route('super_admin.users.suspend', $user->ulid) }}" class="anim-rise ad-2 card card-lift p-5" style="border-top: 4px solid #D97706;">
        @csrf
        <h4 class="font-extrabold text-amber-800">Tangguhkan</h4>
        <p class="mt-1 text-sm text-slate-500">Sementara. Mencabut seluruh tokennya.</p>
        <textarea name="reason" required minlength="10" maxlength="1000" rows="3" class="field mt-3" placeholder="Alasan penangguhan..."></textarea>
        <button class="btn btn-warn w-full mt-3">Tangguhkan</button>
    </form>
    <form method="POST" action="{{ route('super_admin.users.ban', $user->ulid) }}" class="anim-rise ad-3 card card-lift p-5" style="border-top: 4px solid #DC2626;">
        @csrf
        <h4 class="font-extrabold text-red-800">Blokir</h4>
        <p class="mt-1 text-sm text-slate-500">Permanen. Mencabut seluruh tokennya.</p>
        <textarea name="reason" required minlength="10" maxlength="1000" rows="3" class="field mt-3" placeholder="Alasan pemblokiran..."></textarea>
        <button class="btn btn-danger w-full mt-3">Blokir</button>
    </form>
    <form method="POST" action="{{ route('super_admin.users.reinstate', $user->ulid) }}" class="anim-rise ad-4 card card-lift p-5" style="border-top: 4px solid #059669;">
        @csrf
        <h4 class="font-extrabold text-emerald-800">Pulihkan</h4>
        <p class="mt-1 text-sm text-slate-500">Akun yang belum verifikasi email kembali ke <span class="font-mono font-bold">pending_verification</span> — bukan langsung aktif.</p>
        <button class="btn btn-success w-full mt-3">✓ Pulihkan akun</button>
    </form>
</div>
@endsection
