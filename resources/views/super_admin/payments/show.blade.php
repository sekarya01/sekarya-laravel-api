@extends('super_admin.layout')

@section('title', 'Tagihan '.$payment->ulid)
@section('header', 'Detail Tagihan')

@section('content')
<div class="anim-rise flex flex-wrap items-center gap-3">
    <a href="{{ route('super_admin.payments.index') }}" class="btn btn-ghost py-2! text-xs!">← Kembali ke antrean</a>
    @php
        $tone = match($payment->status->value) {
            'held' => 'green', 'released' => 'navy', 'pending' => 'amber',
            'refunded', 'cancelled' => 'slate', default => 'orange',
        };
    @endphp
    @include('super_admin.partials.badge', ['text' => $payment->status->value, 'tone' => $tone])
</div>

<div class="anim-rise mt-3 rounded-2xl p-5 sm:p-6 text-white relative overflow-hidden" style="background: linear-gradient(120deg, #0A1E35, #163C68 70%);">
    <div class="absolute -right-8 -top-12 w-56 h-56 rounded-full opacity-25" style="background: #F97316; filter: blur(60px);"></div>
    <p class="relative text-xs uppercase tracking-widest font-bold text-orange-200">Nominal transfer yang dilaporkan</p>
    <p class="relative mt-1 text-3xl sm:text-4xl font-extrabold">Rp{{ number_format($payment->amount, 0, ',', '.') }}</p>
    <p class="relative mt-1 font-mono text-xs text-slate-400">{{ $payment->ulid }}</p>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="anim-rise ad-1 card p-5 sm:p-6">
        <h4 class="font-extrabold" style="color: #163C68;">Linimasa tagihan</h4>
        @php
            $steps = [
                ['Dilaporkan', $payment->reported_at, true],
                ['Dibayar', $payment->paid_at, (bool) $payment->paid_at],
                ['Ditahan', $payment->held_at, (bool) $payment->held_at],
                ['Dilepas', $payment->released_at, (bool) $payment->released_at],
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
        @if ($payment->rejection_reason)
            <div class="rounded-xl bg-red-50 border border-red-100 p-3 text-sm"><p class="text-xs font-bold text-red-500 uppercase tracking-wide">Alasan penolakan</p><p class="mt-1 text-red-900">{{ $payment->rejection_reason }}</p></div>
        @endif
    </div>

    <div class="anim-rise ad-2 card p-5 sm:p-6">
        <h4 class="font-extrabold" style="color: #163C68;">Task & pembayar</h4>
        <div class="mt-3 rounded-xl p-4 text-white" style="background: linear-gradient(135deg, #163C68, #1E4E85);">
            <p class="font-bold leading-snug">{{ $payment->task?->title }}</p>
            <p class="mt-1 font-mono text-xs text-slate-300">{{ $payment->task?->task_number }} · {{ $payment->task?->status->value }}</p>
            <div class="mt-3 flex items-end justify-between border-t border-white/15 pt-3">
                <span class="text-xs text-slate-300">Total disepakati</span>
                <span class="text-xl font-extrabold text-orange-300">Rp{{ number_format((int) $payment->task?->agreed_amount, 0, ',', '.') }}</span>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-3 text-sm">
            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-xs shrink-0" style="background: #F97316;">
                {{ strtoupper(substr($payment->payer?->name ?? '?', 0, 1)) }}
            </div>
            <div class="min-w-0"><p class="font-bold truncate">{{ $payment->payer?->name }}</p><p class="text-xs text-slate-400 truncate">{{ $payment->payer?->email }}</p></div>
        </div>
        <p class="mt-3 text-xs leading-relaxed text-slate-500 rounded-xl bg-orange-50 border border-orange-100 p-3">
            Angka yang harus sama dengan mutasi rekening adalah <strong>total task</strong>, bukan harga per orang. Harga per orang ada di penawaran masing-masing.
        </p>
    </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <form method="POST" action="{{ route('super_admin.payments.confirm', $payment->ulid) }}" class="anim-rise ad-2 card card-lift p-5 sm:p-6" style="border-top: 4px solid #059669;">
        @csrf
        <h4 class="font-extrabold text-emerald-800">Konfirmasi — mutasi cocok</h4>
        <p class="mt-1 text-sm text-slate-500">Satu-satunya jalan ke <span class="font-mono font-bold">held</span>. Activity dibuka untuk setiap pekerja yang diterima.</p>
        <button class="btn btn-success w-full mt-4">✓ Konfirmasi & tahan dana</button>
    </form>
    <form method="POST" action="{{ route('super_admin.payments.reject', $payment->ulid) }}" class="anim-rise ad-3 card card-lift p-5 sm:p-6" style="border-top: 4px solid #DC2626;">
        @csrf
        <h4 class="font-extrabold text-red-800">Tolak — dana tidak ditemukan</h4>
        <p class="mt-1 text-sm text-slate-500">Kembali ke <span class="font-mono font-bold">pending</span>. Alasan dibaca pemberi kerja.</p>
        <textarea name="reason" required minlength="10" maxlength="500" rows="3" class="field mt-3" placeholder="Tidak ada mutasi masuk sejumlah itu hari ini."></textarea>
        <button class="btn btn-danger w-full mt-3">Tolak laporan</button>
    </form>
</div>
@endsection
