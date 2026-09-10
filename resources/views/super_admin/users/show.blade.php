@extends('super_admin.layout')

@section('title', $user->email)
@section('header', 'Profil Pengguna')

@section('content')
@php
    // Tombol yang tampil mengikuti status — selaras dengan
    // UserStatus::canBeMovedByAdminTo() di sisi Action.
    $st = $user->status->value;
    $canSuspend = in_array($st, ['active', 'pending_verification', 'banned'], true);
    $canBan = in_array($st, ['active', 'pending_verification', 'suspended'], true);
    $canReinstate = in_array($st, ['suspended', 'banned'], true);
    $tone = match($st) {
        'active' => 'green', 'banned' => 'red', 'suspended' => 'orange', default => 'amber',
    };
@endphp

<div class="anim-rise">
    <a href="{{ route('super_admin.users.index') }}" class="btn btn-ghost py-2! text-xs!">← Kembali ke daftar</a>
</div>

<div class="anim-rise ad-1 mt-3 card p-5 sm:p-6 flex flex-wrap items-center gap-4">
    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-xl font-extrabold text-white shrink-0" style="background: linear-gradient(135deg, #163C68, #2A5E9E);">
        {{ strtoupper(substr($user->name, 0, 1)) }}
    </div>
    <div class="min-w-0 flex-1">
        <h3 class="text-xl font-extrabold truncate" style="color: #163C68;">{{ $user->name }}</h3>
        <p class="text-sm text-slate-500 truncate">{{ $user->email }}</p>
    </div>
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

<div class="anim-rise ad-2 mt-4 card p-5 sm:p-6">
    <div class="flex flex-wrap items-center gap-3">
        <h4 class="font-extrabold" style="color: #163C68;">Tindakan moderasi</h4>
        <span class="text-xs text-slate-400">Hanya tindakan yang sah untuk status <span class="font-mono font-bold">{{ $st }}</span> yang tampil.</span>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        @if ($canSuspend)
            <button class="btn btn-warn" data-modal="modal-suspend">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9v6m-4.5 0V9M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Tangguhkan
            </button>
        @endif
        @if ($canBan)
            <button class="btn btn-danger" data-modal="modal-ban">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                Blokir
            </button>
        @endif
        @if ($canReinstate)
            <button class="btn btn-success" data-modal="modal-reinstate">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                Pulihkan
            </button>
        @endif
    </div>
</div>

@if ($canSuspend)
<div id="modal-suspend" class="umodal" role="dialog" aria-modal="true" aria-label="Tangguhkan akun">
    <div class="umodal-bg" data-close></div>
    <div class="umodal-card card w-full max-w-md p-6" style="border-top: 4px solid #D97706;">
        <h4 class="font-extrabold text-lg text-amber-800">Tangguhkan akun ini?</h4>
        <p class="mt-1 text-sm text-slate-500">Sementara. <strong>Seluruh tokennya dicabut</strong> sehingga ia langsung berhenti — bukan delapan jam lagi.</p>
        <form method="POST" action="{{ route('super_admin.users.suspend', $user->ulid) }}" class="mt-4">
            @csrf
            <label class="label" for="suspend-reason">Alasan (min. 10 karakter)</label>
            <textarea id="suspend-reason" name="reason" required minlength="10" maxlength="1000" rows="4" class="field" placeholder="cth. Melaporkan transfer palsu dua kali berturut-turut.">{{ session('open_modal') === 'suspend' ? old('reason') : '' }}</textarea>
            <div class="mt-4 flex gap-2">
                <button type="button" class="btn btn-ghost flex-1" data-close>Batal</button>
                <button class="btn btn-warn flex-1">Ya, tangguhkan</button>
            </div>
        </form>
    </div>
</div>
@endif

@if ($canBan)
<div id="modal-ban" class="umodal" role="dialog" aria-modal="true" aria-label="Blokir akun">
    <div class="umodal-bg" data-close></div>
    <div class="umodal-card card w-full max-w-md p-6" style="border-top: 4px solid #DC2626;">
        <h4 class="font-extrabold text-lg text-red-800">Blokir akun ini?</h4>
        <p class="mt-1 text-sm text-slate-500">Permanen. <strong>Seluruh tokennya dicabut.</strong> Pastikan pelanggaranmu tercatat jelas di bawah.</p>
        <form method="POST" action="{{ route('super_admin.users.ban', $user->ulid) }}" class="mt-4">
            @csrf
            <label class="label" for="ban-reason">Alasan (min. 10 karakter)</label>
            <textarea id="ban-reason" name="reason" required minlength="10" maxlength="1000" rows="4" class="field" placeholder="cth. Terbukti memakai NIK milik orang lain.">{{ session('open_modal') === 'ban' ? old('reason') : '' }}</textarea>
            <div class="mt-4 flex gap-2">
                <button type="button" class="btn btn-ghost flex-1" data-close>Batal</button>
                <button class="btn btn-danger flex-1">Ya, blokir</button>
            </div>
        </form>
    </div>
</div>
@endif

@if ($canReinstate)
<div id="modal-reinstate" class="umodal" role="dialog" aria-modal="true" aria-label="Pulihkan akun">
    <div class="umodal-bg" data-close></div>
    <div class="umodal-card card w-full max-w-md p-6" style="border-top: 4px solid #059669;">
        <h4 class="font-extrabold text-lg text-emerald-800">Pulihkan akun ini?</h4>
        <p class="mt-1 text-sm text-slate-500">Akun yang <strong>belum verifikasi email</strong> kembali ke <span class="font-mono font-bold">pending_verification</span> dan harus menyelesaikan kodenya sendiri — bukan langsung aktif.</p>
        <form method="POST" action="{{ route('super_admin.users.reinstate', $user->ulid) }}" class="mt-4">
            @csrf
            <div class="mt-2 flex gap-2">
                <button type="button" class="btn btn-ghost flex-1" data-close>Batal</button>
                <button class="btn btn-success flex-1">✓ Ya, pulihkan</button>
            </div>
        </form>
    </div>
</div>
@endif

<style>
    .umodal { position: fixed; inset: 0; z-index: 50; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .umodal.open { display: flex; }
    .umodal-bg { position: absolute; inset: 0; background: rgba(10, 30, 53, .6); backdrop-filter: blur(4px); }
    .umodal.open .umodal-bg { animation: umodalFade .25s ease both; }
    .umodal.open .umodal-card { animation: umodalPop .32s cubic-bezier(.22,.8,.32,1) both; }
    @keyframes umodalFade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes umodalPop { from { opacity: 0; transform: translateY(20px) scale(.96); } to { opacity: 1; transform: none; } }
</style>
<script>
    function openUserModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.add('open');
        document.body.style.overflow = 'hidden';
        const t = m.querySelector('textarea');
        if (t) setTimeout(() => t.focus(), 100);
    }
    function closeUserModal(m) {
        m.classList.remove('open');
        if (!document.querySelector('.umodal.open')) document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-modal]').forEach(b =>
        b.addEventListener('click', () => openUserModal(b.dataset.modal)));
    document.querySelectorAll('.umodal').forEach(m =>
        m.querySelectorAll('[data-close]').forEach(c =>
            c.addEventListener('click', () => closeUserModal(m))));
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.querySelectorAll('.umodal.open').forEach(closeUserModal);
    });
    @if (session('open_modal'))
        openUserModal('modal-{{ session('open_modal') }}');
    @endif
</script>
@endsection
