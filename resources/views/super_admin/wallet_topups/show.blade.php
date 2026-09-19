@extends('super_admin.layout')

@section('title', 'Isi saldo '.$topup->ulid)
@section('header', 'Detail Isi Saldo')

@section('content')
<div class="anim-rise flex flex-wrap items-center gap-3">
    <a href="{{ route('super_admin.wallet_topups.index') }}" class="btn btn-ghost py-2! text-xs!">← Kembali ke antrean</a>
    @php
        $tone = match($topup->status->value) {
            'confirmed' => 'green', 'rejected' => 'red',
            'cancelled' => 'slate', default => 'orange',
        };
    @endphp
    @include('super_admin.partials.badge', ['text' => $topup->status->value, 'tone' => $tone])
</div>

<div class="anim-rise mt-3 rounded-2xl p-5 sm:p-6 text-white relative overflow-hidden" style="background: linear-gradient(120deg, #0A1E35, #163C68 70%);">
    <div class="absolute -right-8 -top-12 w-56 h-56 rounded-full opacity-25" style="background: #F97316; filter: blur(60px);"></div>
    <p class="relative text-xs uppercase tracking-widest font-bold text-orange-200">Nominal isi saldo yang dilaporkan</p>
    <p class="relative mt-1 text-3xl sm:text-4xl font-extrabold">Rp{{ number_format($topup->amount, 0, ',', '.') }}</p>
    <p class="relative mt-1 font-mono text-xs text-slate-400">{{ $topup->ulid }}</p>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="anim-rise ad-1 card p-5 sm:p-6">
        <h4 class="font-extrabold" style="color: #163C68;">Linimasa</h4>
        @php
            $end = match($topup->status->value) {
                'rejected' => ['Ditolak', $topup->rejected_at],
                'cancelled' => ['Dibatalkan pengguna', $topup->cancelled_at],
                default => ['Dikonfirmasi', $topup->confirmed_at],
            };
            $steps = [
                ['Diajukan', $topup->created_at, true],
                [$end[0], $end[1], (bool) $end[1]],
            ];
        @endphp
        <ol class="mt-4 space-y-0">
            @foreach ($steps as [$label, $time, $done])
                <li class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <span class="w-3.5 h-3.5 rounded-full border-2 shrink-0 mt-1" style="{{ $done ? 'background:#F97316;border-color:#F97316;' : 'background:#fff;border-color:#CBD5E1;' }}"></span>
                        @if (! $loop->last)<span class="w-1 flex-1 min-h-6" style="background: {{ $done ? '#FBD9B6' : '#E2E8F0' }};"></span>@endif
                    </div>
                    <div class="pb-4">
                        <p class="text-sm font-bold {{ $done ? '' : 'text-slate-400' }}" @if($done) style="color:#163C68;" @endif>{{ $label }}</p>
                        <p class="text-xs text-slate-400">{{ $time ?? 'belum' }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
        @if ($topup->rejection_reason)
            <div class="rounded-xl bg-red-50 border border-red-100 p-3 text-sm"><p class="text-xs font-bold text-red-500 uppercase tracking-wide">Alasan penolakan</p><p class="mt-1 text-red-900">{{ $topup->rejection_reason }}</p></div>
        @endif
    </div>

    <div class="anim-rise ad-2 card p-5 sm:p-6">
        <h4 class="font-extrabold" style="color: #163C68;">Pengguna & pengirim</h4>
        <div class="mt-3 flex items-center gap-3 text-sm">
            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-xs shrink-0" style="background: #F97316;">
                {{ strtoupper(substr($topup->user?->name ?? '?', 0, 1)) }}
            </div>
            <div class="min-w-0"><p class="font-bold truncate">{{ $topup->user?->name }}</p><p class="text-xs text-slate-400 truncate">{{ $topup->user?->email }}</p></div>
        </div>
        <div class="mt-3 rounded-xl p-4 text-white" style="background: linear-gradient(135deg, #163C68, #1E4E85);">
            <p class="text-xs text-slate-300">Catatan pengirim</p>
            <p class="mt-1 font-bold leading-snug">{{ $topup->sender_note ?? '—' }}</p>
            <div class="mt-3 flex items-end justify-between border-t border-white/15 pt-3">
                <span class="text-xs text-slate-300">Saldo pengguna saat ini</span>
                <span class="text-xl font-extrabold text-orange-300">Rp{{ number_format($balance, 0, ',', '.') }}</span>
            </div>
        </div>
        <p class="mt-3 text-xs leading-relaxed text-slate-500 rounded-xl bg-orange-50 border border-orange-100 p-3">
            Konfirmasi hanya kalau mutasi rekening sejumlah <strong>Rp{{ number_format($topup->amount, 0, ',', '.') }}</strong> benar-benar sudah masuk. Saldo yang dikonfirmasi bisa langsung dipakai memasang tugas atau ditarik.
        </p>
    </div>
</div>

@if ($topup->status->awaitsConfirmation())
<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <form method="POST" action="{{ route('super_admin.wallet_topups.confirm', $topup->ulid) }}" class="anim-rise ad-2 card card-lift p-5 sm:p-6" style="border-top: 4px solid #059669;">
        @csrf
        <h4 class="font-extrabold text-emerald-800">Konfirmasi — mutasi cocok</h4>
        <p class="mt-1 text-sm text-slate-500">Saldo pengguna bertambah <strong>Rp{{ number_format($topup->amount, 0, ',', '.') }}</strong> dan tercatat di riwayat dompetnya.</p>
        <button class="btn btn-success w-full mt-4">✓ Konfirmasi & tambah saldo</button>
    </form>
    <form method="POST" action="{{ route('super_admin.wallet_topups.reject', $topup->ulid) }}" class="anim-rise ad-3 card card-lift p-5 sm:p-6" style="border-top: 4px solid #DC2626;">
        @csrf
        <h4 class="font-extrabold text-red-800">Tolak — dana tidak ditemukan</h4>
        <p class="mt-1 text-sm text-slate-500">Saldo tidak berubah. Alasan dibaca pengguna.</p>
        <textarea name="reason" required minlength="10" maxlength="500" rows="3" class="field mt-3" placeholder="Tidak ada mutasi masuk sejumlah itu hari ini.">{{ old('reason') }}</textarea>
        <button class="btn btn-danger w-full mt-3">Tolak isi saldo</button>
    </form>
</div>
@endif
@endsection
