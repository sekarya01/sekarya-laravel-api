@extends('super_admin.layout')

@section('title', 'Tagihan '.$payment->ulid)

@section('content')
<a href="{{ route('super_admin.payments.index') }}" class="text-sm underline">← Kembali ke antrean</a>
<h2 class="mt-2 text-2xl font-semibold">Rp{{ number_format($payment->amount, 0, ',', '.') }} — {{ $payment->status->value }}</h2>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <div class="rounded-xl bg-white p-5 shadow">
        <h3 class="font-semibold">Tagihan</h3>
        <dl class="mt-2 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">ULID</dt><dd class="font-mono text-xs">{{ $payment->ulid }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-mono">{{ $payment->status->value }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Dilaporkan</dt><dd>{{ $payment->reported_at ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Ditahan</dt><dd>{{ $payment->held_at ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Dibayar</dt><dd>{{ $payment->paid_at ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Dilepas</dt><dd>{{ $payment->released_at ?? '—' }}</dd></div>
            @if ($payment->rejection_reason)
                <div><dt class="text-slate-500">Alasan penolakan</dt><dd class="mt-1 rounded bg-slate-100 p-2">{{ $payment->rejection_reason }}</dd></div>
            @endif
        </dl>
    </div>
    <div class="rounded-xl bg-white p-5 shadow">
        <h3 class="font-semibold">Task & pembayar</h3>
        <dl class="mt-2 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Task</dt><dd>{{ $payment->task?->title }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Nomor</dt><dd class="font-mono text-xs">{{ $payment->task?->task_number }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Status task</dt><dd class="font-mono">{{ $payment->task?->status->value }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Total disepakati</dt><dd>Rp{{ number_format((int) $payment->task?->agreed_amount, 0, ',', '.') }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Pembayar</dt><dd>{{ $payment->payer?->email }}</dd></div>
        </dl>
        <p class="mt-3 text-xs text-slate-500">Angka yang harus sama dengan mutasi rekening adalah <strong>total task</strong>, bukan harga per orang. Harga per orang ada di penawaran masing-masing.</p>
    </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <form method="POST" action="{{ route('super_admin.payments.confirm', $payment->ulid) }}" class="rounded-xl bg-white p-5 shadow">
        @csrf
        <h3 class="font-semibold">Konfirmasi — mutasi cocok</h3>
        <p class="text-sm text-slate-500">Satu-satunya jalan ke <span class="font-mono">held</span>. Activity dibuka per pekerja yang diterima.</p>
        <button class="mt-3 w-full rounded bg-emerald-700 px-4 py-2 text-sm text-white">Konfirmasi & tahan dana</button>
    </form>
    <form method="POST" action="{{ route('super_admin.payments.reject', $payment->ulid) }}" class="rounded-xl bg-white p-5 shadow">
        @csrf
        <h3 class="font-semibold">Tolak — dana tidak ditemukan</h3>
        <p class="text-sm text-slate-500">Kembali ke <span class="font-mono">pending</span>. Alasan dibaca pemberi kerja.</p>
        <textarea name="reason" required minlength="10" maxlength="500" rows="3" class="mt-2 w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="Tidak ada mutasi masuk sejumlah itu hari ini."></textarea>
        <button class="mt-3 w-full rounded bg-red-700 px-4 py-2 text-sm text-white">Tolak laporan</button>
    </form>
</div>
@endsection
