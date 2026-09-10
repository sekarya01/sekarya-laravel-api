@extends('super_admin.layout')

@section('title', 'Detail verifikasi #'.$verification->id)
@section('header', 'Detail Verifikasi')

@section('content')
@php
    $tone = match($verification->status->value) {
        'verified' => 'green', 'rejected' => 'red', 'revoked' => 'slate',
        'in_review' => 'navy', default => 'amber',
    };
@endphp

<div class="anim-rise flex flex-wrap items-center gap-3">
    <a href="{{ route('super_admin.verifications.index') }}" class="btn btn-ghost py-2! text-xs!">← Kembali ke antrean</a>
    @include('super_admin.partials.badge', ['text' => $verification->status->value, 'tone' => $tone])
    @include('super_admin.partials.badge', ['text' => $verification->type->value, 'tone' => 'navy'])
</div>

<h3 class="anim-rise mt-3 text-xl sm:text-2xl font-extrabold" style="color: #163C68;">
    Verifikasi #{{ $verification->id }} <span class="text-slate-400 font-semibold">· {{ $verification->user?->name }}</span>
</h3>

<div class="anim-rise ad-1 mt-3 rounded-2xl border border-amber-200 px-4 py-3.5 text-sm text-amber-900 flex gap-3" style="background: linear-gradient(120deg, #FFFBEB, #FFF7E6);">
    <svg class="w-5 h-5 shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
    <p>Membuka halaman ini <strong>mencatat</strong> <span class="font-mono font-bold">verification.viewed</span> ke jejak audit
    (siapa, kapan, dari IP mana). NIK / nomor rekening di bawah hanya ada di halaman ini — tidak pernah di daftar.</p>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-5">
    <div class="anim-rise ad-1 card p-5 sm:p-6 lg:col-span-2">
        <h4 class="font-extrabold" style="color: #163C68;">Status pengajuan</h4>
        <dl class="mt-3 space-y-2.5 text-sm">
            @php
                $rows = [
                    ['Diajukan', (string) $verification->submitted_at],
                    ['Dinilai', (string) ($verification->reviewed_at ?? '—')],
                    ['Penilai', $verification->reviewer?->email ?? '—'],
                    ['Foto KTP', $verification->id_card_photo_path ? 'Ada ✓' : 'Tidak ada'],
                    ['Foto selfie', $verification->selfie_photo_path ? 'Ada ✓' : 'Tidak ada'],
                ];
            @endphp
            @foreach ($rows as [$k, $val])
                <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ $k }}</dt><dd class="font-medium text-right">{{ $val }}</dd></div>
            @endforeach
            @if ($verification->face_match_score !== null)
                <div class="flex justify-between gap-3"><dt class="text-slate-400">Face match</dt><dd class="font-bold" style="color: #F97316;">{{ $verification->face_match_score }}</dd></div>
            @endif
            @if ($verification->rejection_reason)
                <div class="rounded-xl bg-red-50 border border-red-100 p-3"><dt class="text-xs font-bold text-red-500 uppercase tracking-wide">Alasan penolakan</dt><dd class="mt-1 text-red-900">{{ $verification->rejection_reason }}</dd></div>
            @endif
            @if ($verification->revoked_reason)
                <div class="rounded-xl bg-slate-100 border border-slate-200 p-3"><dt class="text-xs font-bold text-slate-500 uppercase tracking-wide">Alasan pencabutan</dt><dd class="mt-1">{{ $verification->revoked_reason }}</dd></div>
            @endif
        </dl>
    </div>

    <div class="anim-rise ad-2 card p-5 sm:p-6 lg:col-span-3" style="border-top: 4px solid #F97316;">
        <h4 class="font-extrabold" style="color: #163C68;">Data dokumen <span class="font-medium text-slate-400 text-xs">· terbaca utuh, keputusan pemilik proyek</span></h4>
        <div class="mt-4 rounded-2xl p-4 sm:p-5" style="background: linear-gradient(135deg, #0A1E35, #163C68);">
            @if ($verification->type->value === 'identity')
                <p class="text-xs uppercase tracking-widest font-bold text-orange-300">NIK KTP</p>
                <p class="mt-1 font-mono text-2xl sm:text-3xl font-bold tracking-wider text-white">{{ $verification->document_number_enc ?? '—' }}</p>
                <div class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
                    <div><p class="text-xs uppercase tracking-wider text-slate-400 font-bold">Nama di dokumen</p><p class="font-semibold text-white">{{ $verification->name_on_document ?? '—' }}</p></div>
                    <div><p class="text-xs uppercase tracking-wider text-slate-400 font-bold">Tanggal lahir</p><p class="font-semibold text-white">{{ $verification->birth_date_on_document ?? '—' }}</p></div>
                </div>
            @else
                <p class="text-xs uppercase tracking-widest font-bold text-orange-300">Nomor rekening</p>
                <p class="mt-1 font-mono text-2xl sm:text-3xl font-bold tracking-wider text-white">{{ $verification->account_number_enc ?? '—' }}</p>
                <div class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
                    <div><p class="text-xs uppercase tracking-wider text-slate-400 font-bold">Bank</p><p class="font-semibold text-white">{{ $verification->bank_code ?? '—' }}</p></div>
                    <div><p class="text-xs uppercase tracking-wider text-slate-400 font-bold">Atas nama</p><p class="font-semibold text-white">{{ $verification->account_holder_name ?? '—' }}</p></div>
                </div>
            @endif
        </div>
        <div class="mt-4 flex items-center gap-3 rounded-xl bg-slate-50 border border-slate-100 p-3.5 text-sm">
            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white shrink-0" style="background: #163C68;">
                {{ strtoupper(substr($verification->user?->name ?? '?', 0, 1)) }}
            </div>
            <div class="min-w-0">
                <p class="font-bold truncate">{{ $verification->user?->name }}</p>
                <p class="text-xs text-slate-500 truncate">{{ $verification->user?->email }} · <span class="font-mono">{{ $verification->user?->status->value }}</span></p>
            </div>
        </div>
    </div>
</div>

<div class="mt-4 grid gap-4 md:grid-cols-3">
    <form method="POST" action="{{ route('super_admin.verifications.approve', $verification) }}" class="anim-rise ad-2 card card-lift p-5" style="border-top: 4px solid #059669;">
        @csrf
        <h4 class="font-extrabold text-emerald-800">Setujui</h4>
        <p class="mt-1 text-sm text-slate-500">Cocok dengan dokumen. Badge terverifikasi pemiliknya menyala.</p>
        <button class="btn btn-success w-full mt-4">✓ Setujui</button>
    </form>
    <form method="POST" action="{{ route('super_admin.verifications.reject', $verification) }}" class="anim-rise ad-3 card card-lift p-5" style="border-top: 4px solid #DC2626;">
        @csrf
        <h4 class="font-extrabold text-red-800">Tolak</h4>
        <p class="mt-1 text-sm text-slate-500">Alasan wajib (min. 10 karakter) — dibaca penggunanya.</p>
        <textarea name="reason" required minlength="10" maxlength="500" rows="3" class="field mt-3" placeholder="Foto KTP tidak terbaca, silakan unggah ulang.">{{ old('reason') }}</textarea>
        <button class="btn btn-danger w-full mt-3">Tolak pengajuan</button>
    </form>
    <form method="POST" action="{{ route('super_admin.verifications.revoke', $verification) }}" class="anim-rise ad-4 card card-lift p-5" style="border-top: 4px solid #163C68;">
        @csrf
        <h4 class="font-extrabold" style="color: #163C68;">Cabut</h4>
        <p class="mt-1 text-sm text-slate-500">Untuk verifikasi yang ternyata palsu. Alasan wajib.</p>
        <textarea name="reason" required minlength="10" maxlength="500" rows="3" class="field mt-3" placeholder="Identitas terbukti palsu berdasarkan ..."></textarea>
        <button class="btn btn-navy w-full mt-3">Cabut verifikasi</button>
    </form>
</div>
@endsection
