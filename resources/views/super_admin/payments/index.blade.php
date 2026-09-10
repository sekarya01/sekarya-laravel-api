@extends('super_admin.layout')

@section('title', 'Antrean transfer')

@section('content')
<h2 class="text-2xl font-semibold">Antrean konfirmasi transfer</h2>
<p class="mt-1 text-sm text-slate-500">Bawaan <span class="font-mono">awaiting_confirmation</span>, urut <span class="font-mono">reported_at</span> — paling lama menunggu di depan.</p>

<form method="GET" class="mt-4 flex gap-2 text-sm">
    <select name="status" class="rounded border border-slate-300 px-3 py-2">
        @foreach (['awaiting_confirmation','pending','held','released','refunded','cancelled'] as $s)
            <option value="{{ $s }}" @selected($filterStatus === $s)>{{ $s }}</option>
        @endforeach
    </select>
    <button class="rounded bg-slate-900 px-4 py-2 text-white">Saring</button>
</form>

<div class="mt-4 overflow-x-auto rounded-xl bg-white shadow">
    <table class="w-full text-sm">
        <thead>
        <tr class="text-left text-slate-500">
            <th class="px-4 py-3">Dilaporkan</th>
            <th class="px-4 py-3">Task</th>
            <th class="px-4 py-3">Nominal</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Pembayar</th>
            <th class="px-4 py-3"></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($queue as $p)
            <tr class="border-t">
                <td class="px-4 py-3 whitespace-nowrap">{{ $p->reported_at ?? $p->created_at }}</td>
                <td class="px-4 py-3">{{ $p->task?->title }} <span class="font-mono text-xs text-slate-500">{{ $p->task?->task_number }}</span></td>
                <td class="px-4 py-3 whitespace-nowrap">Rp{{ number_format($p->amount, 0, ',', '.') }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $p->status->value }}</td>
                <td class="px-4 py-3">{{ $p->payer?->email }}</td>
                <td class="px-4 py-3 text-right"><a href="{{ route('super_admin.payments.show', $p->ulid) }}" class="underline">Buka</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-slate-500">Antrean kosong.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4 flex gap-2 text-sm">
    @if ($queue->previousCursor())
        <a href="{{ $queue->previousPageUrl() }}" class="rounded border px-3 py-2 bg-white">← Sebelumnya</a>
    @endif
    @if ($queue->hasMorePages())
        <a href="{{ $queue->nextPageUrl() }}" class="rounded border px-3 py-2 bg-white">Berikutnya →</a>
    @endif
</div>
@endsection
